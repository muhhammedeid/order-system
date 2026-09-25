<?php

namespace App\Support\WhatsApp\Order;

use App\Contracts\WhatsAppGateway;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Outbound\MessageSender;
use App\Support\WhatsApp\Outbound\OrderConversations;
use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic operational order updates. Called only after a status change has
 * been committed: it never participates in the order transaction and never
 * throws into the caller, so a WhatsApp outage can never fail an order or an
 * admin transition.
 *
 * Customer messages stay order-linked; owner alerts stay unlinked so the
 * customer order panel shows only customer communication. Marketing consent
 * is deliberately not consulted: these are operational messages.
 */
class OrderStatusNotifier
{
    public const PLACED_CUSTOMER = 'order_placed_customer';

    public const PLACED_OWNER = 'order_placed_owner';

    public const CONFIRMED_CUSTOMER = 'order_confirmed_customer';

    public const PARTIALLY_DELIVERED_CUSTOMER = 'order_partially_delivered_customer';

    public const DELIVERED_CUSTOMER = 'order_delivered_customer';

    /**
     * Provider text limit; a rendered body above it is never sent.
     */
    private const MAX_BODY_LENGTH = 4096;

    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly MessageSender $sender,
        private readonly OrderConversations $conversations,
        private readonly WhatsAppTemplateRenderer $renderer,
    ) {}

    /**
     * A newly placed order: the customer receives the order details and the
     * owner receives a new-order alert. The two sends are independent.
     */
    public function orderPlaced(Order $order): OrderNotificationResult
    {
        if (! $this->gateway->enabled()) {
            return new OrderNotificationResult(skipped: ['disabled']);
        }

        return $this->sendToCustomer($order, self::PLACED_CUSTOMER)
            ->merge($this->sendToOwner($order, self::PLACED_OWNER));
    }

    /**
     * A committed status change. `$deliveryEvent` marks a recorded delivery,
     * which notifies even while the order stays `partially_delivered`; a
     * reconciliation without a status change stays silent.
     */
    public function statusChanged(Order $order, OrderStatus $from, bool $deliveryEvent = false): OrderNotificationResult
    {
        $to = $order->status;

        $key = match (true) {
            $to === OrderStatus::Confirmed && $from !== OrderStatus::Confirmed => self::CONFIRMED_CUSTOMER,
            $to === OrderStatus::PartiallyDelivered && ($deliveryEvent || $from !== OrderStatus::PartiallyDelivered) => self::PARTIALLY_DELIVERED_CUSTOMER,
            $to === OrderStatus::Delivered && $from !== OrderStatus::Delivered => self::DELIVERED_CUSTOMER,
            default => null,
        };

        if ($key === null) {
            return new OrderNotificationResult(skipped: ['no_transition']);
        }

        if (! $this->gateway->enabled()) {
            return new OrderNotificationResult(skipped: ['disabled']);
        }

        return $this->sendToCustomer($order, $key);
    }

    private function sendToCustomer(Order $order, string $key): OrderNotificationResult
    {
        $template = $this->activeTemplate($key);

        if ($template === null) {
            $this->logSkip($order, $key, 'template_unavailable');

            return new OrderNotificationResult(skipped: [$key.':template_unavailable']);
        }

        try {
            $body = $this->renderer->renderForOrder($template, $order);
        } catch (WhatsAppTemplateException $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(skipped: [$key.':template_invalid']);
        } catch (Throwable $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(failed: [$key]);
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            $this->logSkip($order, $key, 'body_too_long');

            return new OrderNotificationResult(skipped: [$key.':body_too_long']);
        }

        try {
            $conversation = $this->customerConversation($order);
        } catch (Throwable $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(failed: [$key]);
        }

        if ($conversation === null) {
            $this->logSkip($order, $key, 'customer_unreachable');

            return new OrderNotificationResult(skipped: [$key.':customer_unreachable']);
        }

        return $this->deliver($conversation, $body, $order, $key, $order->id);
    }

    private function sendToOwner(Order $order, string $key): OrderNotificationResult
    {
        $number = Setting::ownerWhatsappNumber();

        if ($number === null) {
            $this->logSkip($order, $key, 'owner_number_missing');

            return new OrderNotificationResult(skipped: [$key.':owner_number_missing']);
        }

        $template = $this->activeTemplate($key);

        if ($template === null) {
            $this->logSkip($order, $key, 'template_unavailable');

            return new OrderNotificationResult(skipped: [$key.':template_unavailable']);
        }

        try {
            $body = $this->renderer->renderForOrder($template, $order);
        } catch (WhatsAppTemplateException $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(skipped: [$key.':template_invalid']);
        } catch (Throwable $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(failed: [$key]);
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            $this->logSkip($order, $key, 'body_too_long');

            return new OrderNotificationResult(skipped: [$key.':body_too_long']);
        }

        try {
            $conversation = $this->ownerConversation($number);
        } catch (Throwable $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(failed: [$key]);
        }

        if ($conversation === null) {
            $this->logSkip($order, $key, 'owner_unreachable');

            return new OrderNotificationResult(skipped: [$key.':owner_unreachable']);
        }

        if ($conversation->customer_id !== null) {
            $this->logSkip($order, $key, 'owner_chat_linked');

            return new OrderNotificationResult(skipped: [$key.':owner_chat_linked']);
        }

        return $this->deliver($conversation, $body, $order, $key, null);
    }

    private function deliver(WhatsAppConversation $conversation, string $body, Order $order, string $key, ?int $orderId): OrderNotificationResult
    {
        try {
            $this->sender->send($conversation, $body, $orderId);
        } catch (Throwable $exception) {
            $this->logFailure($order, $key, $exception);

            return new OrderNotificationResult(failed: [$key]);
        }

        return new OrderNotificationResult(sent: [$key]);
    }

    /**
     * Newest conversation already linked to the order's customer, otherwise
     * first contact through provider number verification.
     */
    private function customerConversation(Order $order): ?WhatsAppConversation
    {
        $existing = $this->conversations->for($order);

        if ($existing->isNotEmpty()) {
            return $existing->first();
        }

        return $this->conversations->firstContact($order);
    }

    /**
     * Owner alert conversation, verified with the provider and never linked
     * to a customer. An existing owner chat is reused only while it is not
     * linked to a customer; a linked chat is returned untouched and the
     * caller refuses to send into a customer thread.
     */
    private function ownerConversation(string $number): ?WhatsAppConversation
    {
        $check = $this->gateway->checkNumber($number);

        if (! $check->exists || $check->chatId === null) {
            return null;
        }

        $conversation = WhatsAppConversation::query()->firstOrCreate(
            ['provider_chat_id' => $check->chatId],
            ['unread_count' => 0],
        );

        if ($conversation->customer_id !== null) {
            return $conversation;
        }

        if ($conversation->resolved_phone === null && $check->phone !== null) {
            $conversation->forceFill(['resolved_phone' => $check->phone])->save();
        }

        return $conversation;
    }

    private function activeTemplate(string $key): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->where('key', $key)
            ->where('active', true)
            ->first();
    }

    private function logSkip(Order $order, string $key, string $reason): void
    {
        Log::info('WhatsApp order notification skipped', [
            'order_id' => $order->id,
            'template' => $key,
            'reason' => $reason,
        ]);
    }

    /**
     * Only our controlled exception messages are logged; unexpected
     * exceptions log their class alone so no chat identity or payload can
     * leak through driver error text.
     */
    private function logFailure(Order $order, string $key, Throwable $exception): void
    {
        Log::warning('WhatsApp order notification failed', [
            'order_id' => $order->id,
            'template' => $key,
            'exception' => $exception::class,
            'reason' => $exception instanceof WhatsAppException || $exception instanceof WhatsAppTemplateException
                ? $exception->getMessage()
                : null,
        ]);
    }
}
