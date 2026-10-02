<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One-to-one WhatsApp conversation keyed by the provider chat id.
 * `resolved_phone` is only ever set from a verified provider identity
 * (`@c.us` or a verified LID lookup); it is never inferred.
 */
class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unread_count' => 0,
    ];

    protected $fillable = [
        'customer_id',
        'provider_chat_id',
        'resolved_phone',
        'last_message_at',
        'last_message_preview',
        'last_message_direction',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function markRead(): void
    {
        if ($this->unread_count > 0) {
            $this->forceFill(['unread_count' => 0])->save();
        }
    }

    public function displayTitle(): string
    {
        $customerName = $this->customer?->name;

        if (filled($customerName)) {
            return $customerName;
        }

        if (filled($this->resolved_phone)) {
            return '+'.$this->resolved_phone;
        }

        return __('admin.whatsapp.inbox.contact_fallback').' '.$this->shortProviderId();
    }

    /**
     * Short, non-identifying suffix used to distinguish unlinked chats in
     * lists. The full provider chat id stays available as a diagnostic.
     */
    public function shortProviderId(): string
    {
        $at = strpos($this->provider_chat_id, '@');
        $local = $at === false ? $this->provider_chat_id : substr($this->provider_chat_id, 0, $at);

        return strlen($local) > 6 ? substr($local, -6) : $local;
    }
}
