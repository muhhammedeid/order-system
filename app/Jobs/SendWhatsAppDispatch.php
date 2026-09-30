<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Customer;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppDispatch;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendWhatsAppDispatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    public function __construct(public readonly int $dispatchId) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(WhatsAppGateway $gateway): void
    {
        $dispatch = WhatsAppDispatch::find($this->dispatchId);

        if ($dispatch === null || $dispatch->status !== 'pending') {
            return;
        }

        if (! $this->eligible($dispatch)) {
            return;
        }

        try {
            if (! $gateway->enabled()) {
                $this->finish($dispatch, 'failed', 'provider_unavailable');

                return;
            }

            $conversation = $this->conversation($dispatch, $gateway);
        } catch (Throwable) {
            $failures = DB::transaction(function () use ($dispatch): int {
                $locked = WhatsAppDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
                if ($locked->status !== 'pending') {
                    return 3;
                }
                $locked->increment('resolution_attempts');

                return $locked->resolution_attempts;
            });
            if ($this->job !== null && $failures < 3) {
                $this->release($this->backoff()[min($failures - 1, 1)]);
            } else {
                $this->finish($dispatch, 'failed', 'recipient_resolution_failed');
            }

            return;
        }

        if ($conversation === null) {
            if ($dispatch->fresh()->status === 'pending') {
                $this->finish($dispatch, 'skipped', 'recipient_unreachable');
            }

            return;
        }

        if ($dispatch->kind === 'campaign' && ! $this->reserveCampaignSend()) {
            return;
        }

        $claimed = DB::transaction(function () use ($dispatch, $conversation): ?WhatsAppMessage {
            $locked = WhatsAppDispatch::query()->lockForUpdate()->find($dispatch->id);
            $chat = WhatsAppConversation::query()->lockForUpdate()->find($conversation->id);

            if ($locked->status !== 'pending' || ! $this->eligible($locked)) {
                return null;
            }

            if (! $this->ownsConversation($locked, $chat)) {
                $this->finish($locked, 'failed', 'conversation_customer_mismatch');

                return null;
            }

            $message = $chat->messages()->create([
                'order_id' => $locked->kind === 'order_customer' ? $locked->order_id : null,
                'direction' => WhatsAppMessageDirection::Outbound,
                'message_type' => $locked->media_url !== null
                    ? WhatsAppMessageType::fromMimeType($locked->media_mimetype) : WhatsAppMessageType::Text,
                'body' => $locked->body,
                'status' => WhatsAppMessageStatus::Pending,
                'occurred_at' => now(),
            ]);

            $locked->forceFill([
                'status' => 'processing',
                'attempts' => $locked->attempts + 1,
                'whatsapp_message_id' => $message->id,
            ])->save();
            WhatsAppCampaignRecipient::query()->where('dispatch_id', $locked->id)->update(['status' => 'processing']);

            return $message;
        });

        if ($claimed === null) {
            return;
        }

        // Once a send starts, any failure can mean the provider accepted it.
        // Neither queue retry nor scheduler recovery may send it again.
        try {
            $sent = $dispatch->media_url !== null
                ? $gateway->sendMedia($conversation->provider_chat_id, [
                    'url' => $dispatch->media_url,
                    'mimetype' => $dispatch->media_mimetype,
                ], $dispatch->body)
                : $gateway->sendText($conversation->provider_chat_id, $dispatch->body);

            if (blank($sent->providerId)) {
                $this->finish($dispatch, 'unknown', 'provider_response_ambiguous');

                return;
            }

            DB::transaction(function () use ($dispatch, $claimed, $conversation, $sent): void {
                $claimed->forceFill(['status' => WhatsAppMessageStatus::Sent, 'provider_message_id' => $sent->providerId])->save();
                $dispatch->forceFill([
                    'status' => 'sent', 'provider_message_id' => $sent->providerId,
                    'sent_at' => now(), 'failure_reason' => null,
                ])->save();
                WhatsAppCampaignRecipient::query()->where('dispatch_id', $dispatch->id)
                    ->update(['status' => 'sent', 'failure_reason' => null]);
                $conversation->forceFill([
                    'last_message_at' => $claimed->occurred_at,
                    'last_message_preview' => $claimed->previewText(),
                    'last_message_direction' => WhatsAppMessageDirection::Outbound->value,
                ])->save();
            });
        } catch (Throwable) {
            $this->finish($dispatch, 'unknown', 'send_outcome_unknown');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $dispatch = WhatsAppDispatch::find($this->dispatchId);

        if ($dispatch !== null && in_array($dispatch->status, ['pending', 'processing'], true)) {
            $this->finish($dispatch, $dispatch->status === 'processing' ? 'unknown' : 'failed', 'worker_failed');
        }
    }

    private function reserveCampaignSend(): bool
    {
        $lock = Cache::lock('whatsapp-campaign-send-lock', 10);
        $delay = 30;
        if ($lock->get()) {
            try {
                $delay = max(0, (int) Cache::get('whatsapp-campaign-next-send', 0) - now()->timestamp);
                if ($delay === 0) {
                    // Shared database cache retains the reservation after worker restart.
                    Cache::put('whatsapp-campaign-next-send', now()->timestamp + 30, 60);

                    return true;
                }
            } finally {
                $lock->release();
            }
        }
        if ($this->job !== null) {
            $this->release($delay);
        }

        return false;
    }

    private function eligible(WhatsAppDispatch $dispatch): bool
    {
        if ($dispatch->kind !== 'campaign') {
            return true;
        }

        $recipient = WhatsAppCampaignRecipient::query()->with('campaign.product')->where('dispatch_id', $dispatch->id)->first();

        if ($recipient === null) {
            $this->finish($dispatch, 'failed', 'campaign_recipient_missing');

            return false;
        }

        if ($recipient->campaign->status === 'paused') {
            return false;
        }

        if ($recipient->campaign->status !== 'running'
            || ! $recipient->campaign->product?->active
            || ! Customer::find($dispatch->customer_id)?->canReceiveWhatsAppMarketing()
            || ! WhatsAppTemplate::find($dispatch->template_id)?->active) {
            $this->finish($dispatch, 'skipped', 'campaign_recipient_ineligible');

            return false;
        }

        return true;
    }

    private function conversation(WhatsAppDispatch $dispatch, WhatsAppGateway $gateway): ?WhatsAppConversation
    {
        if ($dispatch->kind !== 'order_owner' && $dispatch->customer_id === null) {
            return null;
        }

        if ($dispatch->kind !== 'order_owner') {
            $existing = WhatsAppConversation::query()->where('customer_id', $dispatch->customer_id)->latest('last_message_at')->latest('id')->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $check = $gateway->checkNumber(PhoneNumber::normalize($dispatch->recipient_phone));

        if (! $check->exists || $check->chatId === null) {
            return null;
        }

        return DB::transaction(function () use ($dispatch, $check): ?WhatsAppConversation {
            $conversation = WhatsAppConversation::query()->firstOrCreate(
                ['provider_chat_id' => $check->chatId],
                ['resolved_phone' => $check->phone, 'unread_count' => 0],
            );
            $conversation = WhatsAppConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if ($dispatch->kind !== 'order_owner' && $conversation->customer_id === null) {
                $conversation->forceFill(['customer_id' => $dispatch->customer_id])->save();
            }

            if (! $this->ownsConversation($dispatch, $conversation)) {
                $this->finish($dispatch, 'failed', 'conversation_customer_mismatch');

                return null;
            }

            return $conversation;
        });
    }

    private function ownsConversation(WhatsAppDispatch $dispatch, ?WhatsAppConversation $conversation): bool
    {
        return $conversation !== null && ($dispatch->kind === 'order_owner'
            ? $conversation->customer_id === null
            : $dispatch->customer_id !== null && $conversation->customer_id === $dispatch->customer_id);
    }

    private function finish(WhatsAppDispatch $dispatch, string $status, string $reason): void
    {
        DB::transaction(function () use ($dispatch, $status, $reason): void {
            $locked = WhatsAppDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            if (! in_array($locked->status, ['pending', 'processing'], true)) {
                return;
            }
            $locked->forceFill(['status' => $status, 'failure_reason' => $reason])->save();
            if ($locked->whatsapp_message_id !== null) {
                WhatsAppMessage::query()->whereKey($locked->whatsapp_message_id)->update([
                    'status' => $status === 'unknown' ? WhatsAppMessageStatus::Unknown->value : WhatsAppMessageStatus::Failed->value,
                ]);
            }
            WhatsAppCampaignRecipient::query()->where('dispatch_id', $locked->id)
                ->update(['status' => $status, 'failure_reason' => $reason]);
        });
    }
}
