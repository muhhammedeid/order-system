<?php

namespace App\Support\WhatsApp\Outbound;

use App\Contracts\WhatsAppGateway;
use App\Models\Order;
use App\Models\WhatsAppConversation;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the WhatsApp conversation for an order's customer.
 *
 * Conversations are customer/chat scoped: one customer may own several
 * conversations and one conversation may span several orders. Nothing is
 * merged, and a conversation linked to another customer is never reassigned.
 */
class OrderConversations
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    /**
     * @return Collection<int, WhatsAppConversation>
     */
    public function for(Order $order): Collection
    {
        if ($order->customer_id === null) {
            return new Collection;
        }

        return WhatsAppConversation::query()
            ->where('customer_id', $order->customer_id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The number used for first contact: the customer's WhatsApp value when
     * present, otherwise the phone. Neither field is ever modified.
     */
    public function candidateNumber(Order $order): ?string
    {
        $customer = $order->customer;

        if ($customer === null) {
            return null;
        }

        $number = filled($customer->whatsapp) ? $customer->whatsapp : $customer->phone;

        return filled($number) ? (string) $number : null;
    }

    /**
     * Verifies the candidate number and returns a usable conversation shell.
     *
     * Returns null when there is no candidate number or the provider
     * confirmed the number is not a WhatsApp account. Provider transport or
     * response failures throw WhatsAppException and must never be treated as
     * an invalid number.
     *
     * @throws WhatsAppException
     */
    public function firstContact(Order $order): ?WhatsAppConversation
    {
        $number = $this->candidateNumber($order);

        if ($number === null) {
            return null;
        }

        $check = $this->gateway->checkNumber($number);

        if (! $check->exists || $check->chatId === null) {
            return null;
        }

        return DB::transaction(function () use ($order, $check): WhatsAppConversation {
            $conversation = WhatsAppConversation::query()->firstOrCreate(
                ['provider_chat_id' => $check->chatId],
                ['unread_count' => 0],
            );

            $conversation = WhatsAppConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if ($conversation->customer_id !== null && $conversation->customer_id !== $order->customer_id) {
                throw new WhatsAppException('WhatsApp conversation belongs to another customer.');
            }

            if ($conversation->resolved_phone === null && $check->phone !== null) {
                $conversation->forceFill(['resolved_phone' => $check->phone])->save();
            }

            if ($conversation->customer_id === null && $order->customer_id !== null) {
                $conversation->forceFill(['customer_id' => $order->customer_id])->save();
            }

            return $conversation;
        });
    }
}
