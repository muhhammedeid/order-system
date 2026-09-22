<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Customer;
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

    private function template(string $body): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'name' => 'Template '.fake()->unique()->numerify('###'),
            'type' => 'marketing',
            'body' => $body,
        ]);
    }
}
