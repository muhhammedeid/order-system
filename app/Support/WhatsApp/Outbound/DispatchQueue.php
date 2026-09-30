<?php

namespace App\Support\WhatsApp\Outbound;

use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppDispatch;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppDispatch;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchQueue
{
    public function enqueue(array $attributes, string $queue = 'whatsapp-orders'): WhatsAppDispatch
    {
        $dispatch = WhatsAppDispatch::query()->firstOrCreate(
            ['dedupe_key' => $attributes['dedupe_key']],
            array_merge(['status' => 'pending'], $attributes),
        );

        if ($dispatch->status === 'pending') {
            $this->dispatch($dispatch, $queue);
        }

        return $dispatch;
    }

    public function recoverPending(): void
    {
        // A worker may die after the provider accepted a send. Never resend it.
        WhatsAppDispatch::query()->where('status', 'processing')
            ->where('updated_at', '<=', now()->subMinutes(5))->limit(100)->get()
            ->each(function (WhatsAppDispatch $dispatch): void {
                DB::transaction(function () use ($dispatch): void {
                    $locked = WhatsAppDispatch::query()->lockForUpdate()->find($dispatch->id);
                    if ($locked === null || $locked->status !== 'processing'
                        || $locked->updated_at->gt(now()->subMinutes(5))) {
                        return;
                    }
                    $locked->forceFill(['status' => 'unknown', 'failure_reason' => 'worker_interrupted'])->save();
                    WhatsAppMessage::query()->whereKey($locked->whatsapp_message_id)
                        ->update(['status' => WhatsAppMessageStatus::Unknown->value]);
                    WhatsAppCampaignRecipient::query()->where('dispatch_id', $locked->id)
                        ->update(['status' => 'unknown', 'failure_reason' => 'worker_interrupted']);
                });
            });

        WhatsAppDispatch::query()->where('status', 'pending')->where('kind', '!=', 'campaign')->limit(100)->get()->each(function ($dispatch): void {
            $this->dispatch($dispatch, 'whatsapp-orders');
        });
    }

    public function retryFailed(WhatsAppDispatch $dispatch, string $queue = 'whatsapp-orders'): bool
    {
        $updated = WhatsAppDispatch::query()->whereKey($dispatch->id)
            ->where('status', 'failed')->where('attempts', 0)
            ->where('resolution_attempts', '<', 3)
            ->whereNull('provider_message_id')->update(['status' => 'pending', 'failure_reason' => null]);

        if ($updated === 1) {
            $this->dispatch($dispatch->fresh(), $queue);
        }

        return $updated === 1;
    }

    private function dispatch(WhatsAppDispatch $dispatch, string $queue): void
    {
        try {
            DB::afterCommit(function () use ($dispatch, $queue): void {
                try {
                    Bus::dispatch((new SendWhatsAppDispatch($dispatch->id))
                        ->onConnection('database')->onQueue($queue)->beforeCommit());
                } catch (Throwable $exception) {
                    Log::warning('WhatsApp dispatch queue unavailable', [
                        'dispatch_id' => $dispatch->id,
                        'exception' => $exception::class,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('WhatsApp dispatch queue unavailable', [
                'dispatch_id' => $dispatch->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
