<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppCampaignRecipient extends Model
{
    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = ['campaign_id', 'customer_id', 'template_id', 'dispatch_id', 'product_url', 'status', 'failure_reason'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(WhatsAppDispatch::class);
    }

    public function retryBeforeSend(): void
    {
        DB::transaction(function (): void {
            $campaign = WhatsAppCampaign::query()->lockForUpdate()->findOrFail($this->campaign_id);
            $recipient = self::query()->lockForUpdate()->findOrFail($this->id);
            $dispatch = WhatsAppDispatch::query()->lockForUpdate()->find($recipient->dispatch_id);
            if (! in_array($campaign->status, ['running', 'paused', 'completed'], true)
                || $dispatch === null || $dispatch->status !== 'failed'
                || $dispatch->attempts !== 0 || $dispatch->resolution_attempts >= 3 || $dispatch->provider_message_id !== null) {
                throw ValidationException::withMessages(['status' => __('campaigns.unsafe_retry')]);
            }
            $dispatch->update(['status' => 'pending', 'failure_reason' => null]);
            $recipient->update(['status' => 'pending', 'failure_reason' => null]);
            if ($campaign->status === 'completed') {
                $campaign->status = 'paused';
                $campaign->save();
            }
        });
    }
}
