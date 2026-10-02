<?php

namespace App\Models;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A stored conversation message. Provider identity is scoped to the
 * conversation: the unique key is (conversation_id, provider_message_id).
 */
class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'order_id',
        'provider_message_id',
        'direction',
        'message_type',
        'body',
        'status',
        'provider_ack',
        'provider_ack_name',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => WhatsAppMessageDirection::class,
            'message_type' => WhatsAppMessageType::class,
            'status' => WhatsAppMessageStatus::class,
            'provider_ack' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === WhatsAppMessageDirection::Inbound;
    }

    public function isOutbound(): bool
    {
        return $this->direction === WhatsAppMessageDirection::Outbound;
    }

    /**
     * Applies a provider acknowledgement. The raw ack values are always
     * preserved; the application status only moves forward, `failed` is
     * terminal, and unknown ack names leave the status untouched.
     */
    public function applyProviderAck(?int $ack, ?string $ackName): bool
    {
        $attributes = [
            'provider_ack' => $ack,
            'provider_ack_name' => $ackName,
        ];

        $newStatus = WhatsAppMessageStatus::fromAckName($ackName);
        $statusChanged = $newStatus !== null && $newStatus->canBeAppliedTo($this->status);

        if ($statusChanged) {
            $attributes['status'] = $newStatus;
        }

        $this->forceFill($attributes)->save();

        return $statusChanged;
    }

    /**
     * Conversation-list preview. Non-text messages never store a body in
     * this package, so they have no preview text.
     */
    public function previewText(): ?string
    {
        if (! $this->message_type->isText() || blank($this->body)) {
            return null;
        }

        return Str::limit(trim($this->body), 160, '');
    }
}
