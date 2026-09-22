<?php

namespace Tests\Feature\WhatsApp;

use App\Support\WhatsApp\Order\OrderMessageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class OrderMessageTemplatesTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    public function test_each_status_maps_to_its_template_with_order_number_and_customer(): void
    {
        $cases = [
            'new' => OrderMessageTemplates::ORDER_RECEIVED,
            'confirmed' => OrderMessageTemplates::ORDER_CONFIRMED,
            'partially_delivered' => OrderMessageTemplates::PARTIAL_DELIVERY,
            'delivered' => OrderMessageTemplates::ORDER_DELIVERED,
            'cancelled' => OrderMessageTemplates::ORDER_CANCELLED,
        ];

        foreach ($cases as $status => $key) {
            $order = $this->createOrderWithCustomer(
                ['name' => 'Acme Store'],
                ['status' => $status],
                quantity: 10,
                delivered: $status === 'partially_delivered' ? 4 : 0,
            )->load('items', 'customer');

            $templates = OrderMessageTemplates::for($order);

            $this->assertCount(1, $templates);
            $this->assertSame($key, $templates[0]['key'], $status);
            $this->assertNotSame('', $templates[0]['label']);
            $this->assertStringContainsString($order->order_number, $templates[0]['body']);
            $this->assertStringContainsString('Acme Store', $templates[0]['body']);
        }
    }

    public function test_partial_delivery_uses_the_approved_delivered_and_remaining_totals(): void
    {
        $order = $this->createOrderWithCustomer(
            [],
            ['status' => 'partially_delivered'],
            quantity: 10,
            delivered: 4,
        )->load('items', 'customer');

        $body = OrderMessageTemplates::for($order)[0]['body'];

        $this->assertStringContainsString('4', $body);
        $this->assertStringContainsString('6', $body);
    }

    public function test_internal_notes_and_prices_never_appear_in_the_text(): void
    {
        $order = $this->createOrderWithCustomer(
            [],
            [
                'status' => 'confirmed',
                'admin_notes' => 'INTERNAL_SECRET_NOTE',
                'customer_notes' => 'CUSTOMER_SECRET_NOTE',
            ],
            quantity: 10,
        )->load('items', 'customer');

        $body = OrderMessageTemplates::for($order)[0]['body'];

        $this->assertStringNotContainsString('INTERNAL_SECRET_NOTE', $body);
        $this->assertStringNotContainsString('CUSTOMER_SECRET_NOTE', $body);
    }

    public function test_is_available_only_accepts_the_template_for_the_current_status(): void
    {
        $order = $this->createOrderWithCustomer([], ['status' => 'confirmed'], quantity: 10);

        $this->assertTrue(OrderMessageTemplates::isAvailable(OrderMessageTemplates::ORDER_CONFIRMED, $order));
        $this->assertFalse(OrderMessageTemplates::isAvailable(OrderMessageTemplates::ORDER_DELIVERED, $order));
        $this->assertFalse(OrderMessageTemplates::isAvailable('not_a_template', $order));
    }
}
