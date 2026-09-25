<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppTemplateType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\Templates\WhatsAppTemplateException;
use App\Support\WhatsApp\Templates\WhatsAppTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->renderer = new WhatsAppTemplateRenderer;
    }

    public function test_it_renders_all_approved_variables_as_plain_text(): void
    {
        Setting::set('business_name', 'MAI Trading');

        $customer = Customer::factory()->create(['name' => 'Acme Store']);
        $product = Product::factory()->create(['name' => 'Runner', 'product_code' => 'SH-1']);

        $template = $this->template(
            'Hello {{ customer_name }}, {{product_name}} ({{ product_code }}) from {{business_name}}.'
        );

        $this->assertSame(
            'Hello Acme Store, Runner (SH-1) from MAI Trading.',
            $this->renderer->render($template, $customer, $product),
        );
    }

    public function test_business_name_prefers_the_settings_value_over_the_application_name(): void
    {
        config()->set('app.name', 'TECHNICAL_NAME');

        $customer = Customer::factory()->create(['name' => 'Acme']);
        $template = $this->template('{{business_name}}');

        $this->assertSame('TECHNICAL_NAME', $this->renderer->render($template, $customer));

        Setting::set('business_name', 'BUSINESS_NAME');

        $this->assertSame('BUSINESS_NAME', $this->renderer->render($template, $customer));

        config()->set('app.name', 'RENAMED_TECHNICAL_NAME');

        $this->assertSame('BUSINESS_NAME', $this->renderer->render($template, $customer));
    }

    public function test_empty_business_name_setting_falls_back_to_the_application_name(): void
    {
        config()->set('app.name', 'FALLBACK_NAME');
        Setting::set('business_name', '   ');

        $customer = Customer::factory()->create(['name' => 'Acme']);

        $this->assertSame('FALLBACK_NAME', $this->renderer->render($this->template('{{business_name}}'), $customer));
    }

    public function test_product_variables_fail_closed_without_a_product(): void
    {
        $template = $this->template('{{product_name}}');

        $this->expectException(WhatsAppTemplateException::class);

        $this->renderer->render($template, Customer::factory()->create());
    }

    public function test_unknown_variables_are_rejected_at_render_time(): void
    {
        $template = $this->template('{{ 1+1 }}');

        $this->expectException(WhatsAppTemplateException::class);
        $this->expectExceptionMessage(__('admin.whatsapp.templates.errors.unknown_tokens', ['tokens' => '1+1']));

        $this->renderer->render($template, Customer::factory()->create());
    }

    public function test_replacement_is_single_pass_and_never_recursive(): void
    {
        $customer = Customer::factory()->create(['name' => '{{product_name}}']);

        $rendered = $this->renderer->render($this->template('Hello {{customer_name}}'), $customer);

        $this->assertSame('Hello {{product_name}}', $rendered);
    }

    public function test_inserted_values_are_trimmed_and_stripped_of_control_characters(): void
    {
        $customer = Customer::factory()->create(['name' => "  Acme\x07 Store\n"]);

        $rendered = $this->renderer->render($this->template('[{{customer_name}}]'), $customer);

        $this->assertSame('[Acme Store]', $rendered);
    }

    public function test_scalar_values_are_collapsed_to_one_safe_line(): void
    {
        $customer = Customer::factory()->create(['name' => "Acme\nInjected\u{202E}Line"]);

        $rendered = $this->renderer->render($this->template('[{{customer_name}}]'), $customer);

        $this->assertSame('[Acme Injected Line]', $rendered);
        $this->assertStringNotContainsString("\u{202E}", $rendered);
        $this->assertStringNotContainsString("\n", $rendered);
    }

    public function test_order_variables_render_from_order_snapshots(): void
    {
        Setting::set('business_name', 'MAI Trading');
        config()->set('whatsapp.order_locale', 'en');

        $order = $this->orderWithItem(quantity: 10, delivered: 4);

        $template = $this->template(
            '{{customer_name}}|{{order_number}}|{{order_status}}|{{total_quantity}}|{{delivered_quantity}}|{{remaining_quantity}}|{{business_name}}',
            WhatsAppTemplateType::Order,
        );

        $this->assertSame(
            'Acme Store|'.$order->order_number.'|New|10|4|6|MAI Trading',
            $this->renderer->renderForOrder($template, $order),
        );
    }

    public function test_order_items_are_rendered_from_snapshots_without_prices_or_notes(): void
    {
        $order = $this->orderWithItem(quantity: 5);
        $item = $order->items()->firstOrFail();

        $body = $this->renderer->renderForOrder($this->template('{{order_items}}', WhatsAppTemplateType::Order), $order);

        $this->assertStringContainsString($item->product_name, $body);
        $this->assertStringContainsString($item->product_code, $body);
        $this->assertStringContainsString((string) $item->color, $body);
        $this->assertStringContainsString('× 5', $body);
        $this->assertStringNotContainsString((string) $item->unit_price, $body);
        $this->assertStringNotContainsString('INTERNAL_SECRET_NOTE', $body);
    }

    public function test_order_variables_fail_closed_without_an_order(): void
    {
        $template = $this->template('{{order_number}}', WhatsAppTemplateType::Order);

        $this->expectException(WhatsAppTemplateException::class);
        $this->expectExceptionMessage(__('admin.whatsapp.templates.errors.missing_order'));

        $this->renderer->render($template, Customer::factory()->create());
    }

    public function test_order_status_uses_the_customer_locale_not_the_session_locale(): void
    {
        config()->set('whatsapp.order_locale', 'ar');
        app()->setLocale('en');

        $template = $this->template('{{order_status}}', WhatsAppTemplateType::Order);

        $this->assertSame('جديد', $this->renderer->renderForOrder($template, $this->orderWithItem(quantity: 5)));
    }

    public function test_product_variables_fail_closed_for_order_templates(): void
    {
        $template = $this->template('{{product_name}}', WhatsAppTemplateType::Order);

        $this->expectException(WhatsAppTemplateException::class);
        $this->expectExceptionMessage(__('admin.whatsapp.templates.errors.missing_product'));

        $this->renderer->renderForOrder($template, $this->orderWithItem(quantity: 5));
    }

    private function orderWithItem(int $quantity = 5, int $delivered = 0): Order
    {
        $customer = Customer::factory()->create(['name' => 'Acme Store']);

        $product = Product::factory()->create([
            'name' => 'Runner',
            'product_code' => 'SH-1',
            'price_visibility' => 'public',
            'price' => 450,
            'size_enabled' => true,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => 10,
        ]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => $customer->id,
            'admin_notes' => 'INTERNAL_SECRET_NOTE',
            'total_quantity' => 0,
        ]);

        $item = $order->items()->create(OrderItem::snapshotFromVariant($variant) + [
            'quantity' => $quantity,
        ]);

        if ($delivered > 0) {
            $item->forceFill(['delivered_quantity' => $delivered])->save();
        }

        $order->recalculateTotalQuantity();

        return $order->refresh();
    }

    private function template(string $body, WhatsAppTemplateType $type = WhatsAppTemplateType::Marketing): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'name' => 'Template '.fake()->unique()->numerify('###'),
            'type' => $type,
            'body' => $body,
        ]);
    }
}
