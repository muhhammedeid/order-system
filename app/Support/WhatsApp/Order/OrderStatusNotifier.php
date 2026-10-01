<?php

namespace App\Support\WhatsApp\Order;

use App\Contracts\WhatsAppGateway;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Outbound\DispatchQueue;
use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderStatusNotifier
{
    public const PLACED_CUSTOMER = 'order_placed_customer';

    public const PLACED_OWNER = 'order_placed_owner';

    public const CONFIRMED_CUSTOMER = 'order_confirmed_customer';

    public const PARTIALLY_DELIVERED_CUSTOMER = 'order_partially_delivered_customer';

    public const DELIVERED_CUSTOMER = 'order_delivered_customer';

    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly DispatchQueue $queue,
        private readonly WhatsAppTemplateRenderer $renderer,
    ) {}

    public function orderPlaced(Order $order): OrderNotificationResult
    {
        if (! $this->gateway->enabled()) {
            return new OrderNotificationResult(skipped: ['disabled']);
        }

        return $this->enqueue($order, self::PLACED_CUSTOMER)
            ->merge($this->enqueue($order, self::PLACED_OWNER, owner: true));
    }

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

        return $this->enqueue($order, $key);
    }

    private function enqueue(Order $order, string $key, bool $owner = false): OrderNotificationResult
    {
        if (! ($owner ? Setting::whatsappManagerNotificationsEnabled() : Setting::whatsappCustomerNotificationsEnabled())) {
            return new OrderNotificationResult(skipped: [$key.':automatic_notifications_disabled']);
        }

        $template = WhatsAppTemplate::query()->where('key', $key)->where('active', true)->first();

        if ($template === null) {
            return new OrderNotificationResult(skipped: [$key.':template_unavailable']);
        }

        $phone = $owner ? Setting::ownerWhatsappNumber()
            : ($order->customer?->whatsapp ?: $order->customer?->phone);

        if (blank($phone)) {
            return new OrderNotificationResult(skipped: [$key.($owner ? ':owner_number_missing' : ':customer_unreachable')]);
        }

        try {
            $body = $owner ? $this->renderer->renderForOwner($template, $order)
                : $this->renderer->renderForOrder($template, $order);

            if (mb_strlen($body) > 4096) {
                return new OrderNotificationResult(skipped: [$key.':body_too_long']);
            }

            $fingerprint = $key === self::PARTIALLY_DELIVERED_CUSTOMER
                ? ':'.hash('sha256', $order->items()->orderBy('id')->get(['id', 'delivered_quantity'])->toJson())
                : '';

            $this->queue->enqueue([
                'dedupe_key' => 'order:'.$order->id.':'.$key.$fingerprint,
                'kind' => $owner ? 'order_owner' : 'order_customer',
                'order_id' => $order->id,
                'customer_id' => $owner ? null : $order->customer_id,
                'template_id' => $template->id,
                'recipient_phone' => $phone,
                'body' => $body,
            ]);
        } catch (WhatsAppTemplateException) {
            return new OrderNotificationResult(skipped: [$key.':template_invalid']);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp order notification could not be queued', [
                'order_id' => $order->id, 'template' => $key, 'exception' => $exception::class,
            ]);

            return new OrderNotificationResult(failed: [$key]);
        }

        return new OrderNotificationResult(queued: [$key]);
    }
}
