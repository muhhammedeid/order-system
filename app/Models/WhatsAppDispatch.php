<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppDispatch extends Model
{
    protected $table = 'whatsapp_dispatches';

    protected $fillable = [
        'dedupe_key', 'kind', 'order_id', 'customer_id', 'template_id',
        'recipient_phone', 'body', 'media_url', 'media_mimetype', 'status',
        'attempts', 'resolution_attempts', 'sent_at', 'failure_reason', 'provider_message_id', 'whatsapp_message_id',
    ];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'resolution_attempts' => 'integer', 'sent_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class);
    }
}
