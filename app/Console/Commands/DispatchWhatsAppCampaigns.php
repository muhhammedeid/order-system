<?php

namespace App\Console\Commands;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Support\WhatsApp\Outbound\DispatchQueue;
use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DispatchWhatsAppCampaigns extends Command
{
    protected $signature = 'whatsapp:dispatch-campaigns';

    protected $description = 'Recover pending order delivery and enqueue one due campaign recipient';

    public function handle(DispatchQueue $queue, WhatsAppTemplateRenderer $renderer): int
    {
        // A global cadence prevents multiple campaigns multiplying the send rate.
        // The scheduler/DB worker performs delivery; this command never calls a provider.
        return Cache::lock('whatsapp-campaign-dispatch', 20)->get(function () use ($queue, $renderer): int {
            $queue->recoverPending();
            if ((int) Cache::get('whatsapp-campaign-next-dispatch', 0) > now()->timestamp) {
                return self::SUCCESS;
            }
            DB::transaction(function () use ($queue, $renderer): void {
                $campaigns = WhatsAppCampaign::query()->where('status', 'running')
                    ->where('next_dispatch_at', '<=', now())->orderBy('next_dispatch_at')->orderBy('id')->limit(100)->lockForUpdate()->get();
                foreach ($campaigns as $campaign) {
                    $recipient = $campaign->recipients()->with(['customer', 'template', 'dispatch'])
                        ->where('status', 'pending')
                        ->where(fn ($query) => $query->whereNull('dispatch_id')->orWhereHas('dispatch', fn ($dispatch) => $dispatch->where('status', 'pending')))
                        ->orderBy('id')->lockForUpdate()->first();
                    if ($recipient === null) {
                        if (! $campaign->recipients()->whereHas('dispatch', fn ($query) => $query->whereIn('status', ['pending', 'processing']))->exists()) {
                            $campaign->status = 'completed';
                            $campaign->save();
                        }

                        continue;
                    }
                    Cache::put('whatsapp-campaign-next-dispatch', now()->addSeconds(30)->timestamp, 60);
                    if (! $campaign->product->active || ! $recipient->customer->canReceiveWhatsAppMarketing()
                        || ! $recipient->template->active || $recipient->template->type->value !== 'product_announcement') {
                        $this->skip($recipient, 'Campaign eligibility changed before dispatch.');
                    } else {
                        if ($recipient->dispatch !== null) {
                            // Keep the first accepted message/media/URL snapshot during recovery.
                            $queue->enqueue($recipient->dispatch->getAttributes(), 'whatsapp-campaigns');
                            $campaign->next_dispatch_at = now()->addSeconds(30);
                            $campaign->save();

                            return;
                        }
                        try {
                            $body = $renderer->render($recipient->template, $recipient->customer, $campaign->product);
                        } catch (WhatsAppTemplateException) {
                            $this->skip($recipient, 'Template could not be rendered safely.');
                            $campaign->next_dispatch_at = now()->addSeconds(30);
                            $campaign->save();

                            return;
                        }
                        $image = $campaign->product->images()->orderBy('sort_order')->orderBy('id')->first();
                        $dispatch = $queue->enqueue([
                            'dedupe_key' => "campaign:{$campaign->id}:customer:{$recipient->customer_id}",
                            'kind' => 'campaign',
                            'customer_id' => $recipient->customer_id,
                            'template_id' => $recipient->template_id,
                            'recipient_phone' => filled($recipient->customer->whatsapp) ? $recipient->customer->whatsapp : $recipient->customer->phone,
                            'body' => $body,
                            'media_url' => $image?->url(),
                            'media_mimetype' => $image ? match (strtolower(pathinfo($image->image_path, PATHINFO_EXTENSION))) {
                                'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif', default => 'image/jpeg',
                            } : null,
                        ], 'whatsapp-campaigns');
                        $recipient->update(['dispatch_id' => $dispatch->id, 'product_url' => route('product.show', $campaign->product)]);
                    }
                    $campaign->next_dispatch_at = now()->addSeconds(30);
                    $campaign->save();

                    return;
                }
            });

            return self::SUCCESS;
        }) ?? self::SUCCESS;
    }

    private function skip(WhatsAppCampaignRecipient $recipient, string $reason): void
    {
        $recipient->update(['status' => 'skipped', 'failure_reason' => $reason]);
        if ($recipient->dispatch?->status === 'pending') {
            $recipient->dispatch->update(['status' => 'skipped', 'failure_reason' => $reason]);
        }
    }
}
