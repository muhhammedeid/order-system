<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppTemplateType;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class TemplatePrivacyContextTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    public function test_owner_context_contains_required_fields_but_customer_render_rejects_them(): void
    {
        $order = $this->createOrderWithCustomer(['phone' => '01001234567']);
        $template = WhatsAppTemplate::create([
            'name' => 'Owner', 'type' => WhatsAppTemplateType::OrderUpdate,
            'body' => '{{order_number}} {{customer_name}} {{customer_phone}} {{total_quantity}} {{admin_order_url}}',
        ]);
        $renderer = app(WhatsAppTemplateRenderer::class);
        $body = $renderer->renderForOwner($template, $order);
        $this->assertStringContainsString($order->order_number, $body);
        $this->assertStringContainsString('01001234567', $body);
        $this->assertStringContainsString('/admin/order-management/'.$order->id, $body);
        $this->expectException(WhatsAppTemplateException::class);
        $renderer->renderForOrder($template, $order);
    }

    public function test_existing_owner_template_edits_survive_and_required_fields_are_completed(): void
    {
        $order = $this->createOrderWithCustomer(['phone' => '01001234567']);
        $template = WhatsAppTemplate::create([
            'key' => 'order_placed_owner', 'name' => 'Edited owner', 'type' => WhatsAppTemplateType::Order,
            'body' => 'CUSTOM OWNER COPY {{customer_name}}',
        ]);
        $body = app(WhatsAppTemplateRenderer::class)->renderForOwner($template, $order);
        $this->assertStringContainsString('CUSTOM OWNER COPY', $body);
        $this->assertStringContainsString('01001234567', $body);
        $this->assertStringContainsString($order->order_number, $body);
        $this->assertStringContainsString('/admin/order-management/'.$order->id, $body);
        $this->assertStringContainsString('إجمالي القطع:', $body);
        $this->assertSame('CUSTOM OWNER COPY {{customer_name}}', $template->fresh()->body);
    }

    public function test_product_url_is_public_and_internal_variables_fail_closed(): void
    {
        $order = $this->createOrderWithCustomer();
        $product = $order->items->first()->productVariant->product;
        $template = WhatsAppTemplate::create([
            'name' => 'Announcement', 'type' => WhatsAppTemplateType::ProductAnnouncement,
            'body' => '{{product_name}} {{product_code}} {{product_url}}',
        ]);
        $renderer = app(WhatsAppTemplateRenderer::class);
        $body = $renderer->render($template, $order->customer, $product);
        $this->assertStringContainsString(route('product.show', $product), $body);
        $template->body = '{{price}} {{available_quantity}} {{admin_notes}}';
        $this->expectException(WhatsAppTemplateException::class);
        $renderer->render($template, $order->customer, $product);
    }
}
