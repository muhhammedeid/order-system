<?php

namespace Tests\Feature\WhatsApp;

use App\Filament\Resources\WhatsAppTemplates\Pages\CreateWhatsAppTemplate;
use App\Filament\Resources\WhatsAppTemplates\Pages\EditWhatsAppTemplate;
use App\Filament\Resources\WhatsAppTemplates\Pages\ListWhatsAppTemplates;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class WhatsAppTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
    }

    public function test_guest_cannot_reach_the_templates_resource(): void
    {
        $this->get('/admin/whatsapp-templates')->assertRedirect('/admin/login');
    }

    public function test_admin_can_list_create_edit_and_delete_templates(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/whatsapp-templates')->assertOk();
        $this->actingAs($admin)->get('/admin/whatsapp-templates/create')->assertOk();

        Livewire::actingAs($admin)
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Welcome offer',
                'type' => 'marketing',
                'body' => 'Hello {{customer_name}}',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = WhatsAppTemplate::query()->firstOrFail();

        $this->assertSame('Welcome offer', $template->name);
        $this->assertSame('marketing', $template->type->value);
        $this->assertTrue($template->active);

        $this->actingAs($admin)->get("/admin/whatsapp-templates/{$template->getKey()}/edit")->assertOk();

        Livewire::actingAs($admin)
            ->test(EditWhatsAppTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($template->refresh()->active);

        Livewire::actingAs($admin)
            ->test(EditWhatsAppTemplate::class, ['record' => $template->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('whatsapp_templates', ['id' => $template->getKey()]);
    }

    public function test_template_name_must_be_unique(): void
    {
        $this->template(['name' => 'Duplicate']);

        Livewire::actingAs(User::factory()->create())
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Duplicate',
                'type' => 'general',
                'body' => 'Hello',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertDatabaseCount('whatsapp_templates', 1);
    }

    public function test_unknown_variables_are_rejected_before_saving(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Unknown variable',
                'type' => 'general',
                'body' => 'Total: {{order_total}}',
            ])
            ->call('create')
            ->assertHasFormErrors(['body']);

        $this->assertDatabaseCount('whatsapp_templates', 0);
    }

    public function test_body_is_required_and_capped_at_4096_characters(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Too long',
                'type' => 'general',
                'body' => str_repeat('a', 4097),
            ])
            ->call('create')
            ->assertHasFormErrors(['body']);

        Livewire::actingAs($admin)
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Max length',
                'type' => 'general',
                'body' => str_repeat('a', 4096),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(4096, strlen(WhatsAppTemplate::query()->firstOrFail()->body));
    }

    public function test_preview_renders_real_customer_and_product_context(): void
    {
        Setting::set('business_name', 'MAI Trading');

        $template = $this->template([
            'name' => 'Offer',
            'type' => 'marketing',
            'body' => 'Hello {{customer_name}}, {{product_name}} ({{product_code}}) from {{business_name}}.',
        ]);
        $customer = Customer::factory()->create(['name' => 'Acme Store']);
        $product = Product::factory()->create(['name' => 'Runner', 'product_code' => 'SH-1']);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->mountTableAction('preview', $template)
            ->setTableActionData(['customer_id' => $customer->id, 'product_id' => $product->id]);

        $this->assertStringContainsString(
            'Hello Acme Store, Runner (SH-1) from MAI Trading.',
            $this->previewHtml($component),
        );
    }

    public function test_preview_escapes_inserted_values(): void
    {
        $template = $this->template(['body' => '{{customer_name}}']);
        $customer = Customer::factory()->create(['name' => '<script>XSS_MARKER</script>']);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->mountTableAction('preview', $template)
            ->setTableActionData(['customer_id' => $customer->id]);

        $html = $this->previewHtml($component);

        $this->assertStringContainsString('&lt;script&gt;XSS_MARKER&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>XSS_MARKER</script>', $html);
    }

    public function test_preview_reports_missing_product_context_instead_of_rendering(): void
    {
        $template = $this->template(['body' => '{{product_name}}']);
        $customer = Customer::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->mountTableAction('preview', $template)
            ->setTableActionData(['customer_id' => $customer->id]);

        $this->assertStringContainsString(
            __('admin.whatsapp.templates.errors.missing_product'),
            $this->previewHtml($component),
        );
    }

    public function test_preview_never_executes_php_or_blade_like_input(): void
    {
        $template = $this->template(['body' => '{{ 1+1 }} and {{ 7*7 }}']);
        $customer = Customer::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->mountTableAction('preview', $template)
            ->setTableActionData(['customer_id' => $customer->id]);

        $html = $this->previewHtml($component);

        $this->assertStringContainsString(
            __('admin.whatsapp.templates.errors.unknown_tokens', ['tokens' => '1+1, 7*7']),
            $html,
        );
        $this->assertStringNotContainsString('2 and 49', $html);
    }

    public function test_preview_asks_for_a_customer_before_rendering(): void
    {
        $template = $this->template(['body' => 'Hello {{customer_name}}']);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->mountTableAction('preview', $template);

        $this->assertStringContainsString(
            __('admin.whatsapp.templates.preview.choose_customer'),
            $this->previewHtml($component),
        );
    }

    /**
     * Filament renders action modals as a Livewire partial, exposed on the
     * test response effects rather than the page HTML.
     */
    private function previewHtml(Testable $component): string
    {
        return implode('', $component->effects['partials'] ?? []);
    }

    public function test_order_template_preview_renders_with_a_selected_order(): void
    {
        $template = $this->template([
            'name' => 'Order update',
            'type' => 'order',
            'body' => 'Order {{order_number}} for {{customer_name}}',
        ]);

        $customer = Customer::factory()->create(['name' => 'Acme Store']);
        $order = Order::create([
            'order_number' => 'ORD-2026-00042',
            'customer_id' => $customer->id,
            'total_quantity' => 0,
        ]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->mountTableAction('preview', $template)
            ->setTableActionData(['order_id' => $order->id]);

        $this->assertStringContainsString(
            'Order ORD-2026-00042 for Acme Store',
            $this->previewHtml($component),
        );
    }

    public function test_system_template_key_is_read_only_and_delete_is_hidden(): void
    {
        $admin = User::factory()->create();
        $template = $this->template(['key' => 'order_delivered_customer', 'type' => 'order']);

        Livewire::actingAs($admin)
            ->test(ListWhatsAppTemplates::class)
            ->assertTableActionHidden('delete', $template);

        Livewire::actingAs($admin)
            ->test(EditWhatsAppTemplate::class, ['record' => $template->getKey()])
            ->assertFormFieldDisabled('key')
            ->assertFormFieldDisabled('type')
            ->assertActionHidden('delete');
    }

    public function test_system_template_model_refuses_deletion(): void
    {
        $template = $this->template(['key' => 'order_delivered_customer', 'type' => 'order']);

        $this->expectException(RuntimeException::class);

        $template->delete();
    }

    public function test_system_template_body_is_editable_while_key_and_type_stay_locked(): void
    {
        $admin = User::factory()->create();
        $template = $this->template([
            'key' => 'order_confirmed_customer',
            'type' => 'order',
            'body' => 'OLD BODY',
        ]);

        Livewire::actingAs($admin)
            ->test(EditWhatsAppTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['body' => 'NEW BODY {{customer_name}}'])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();

        $this->assertSame('NEW BODY {{customer_name}}', $template->body);
        $this->assertSame('order_confirmed_customer', $template->key);
        $this->assertSame('order', $template->type->value);
    }

    public function test_order_templates_reject_product_variables_at_save(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Bad order template',
                'type' => 'order',
                'body' => '{{product_name}}',
            ])
            ->call('create')
            ->assertHasFormErrors(['body']);

        $this->assertDatabaseCount('whatsapp_templates', 0);
    }

    public function test_non_order_templates_reject_order_variables_at_save(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateWhatsAppTemplate::class)
            ->fillForm([
                'name' => 'Bad marketing template',
                'type' => 'marketing',
                'body' => '{{order_number}}',
            ])
            ->call('create')
            ->assertHasFormErrors(['body']);

        $this->assertDatabaseCount('whatsapp_templates', 0);
    }

    public function test_templates_list_body_column_is_toggleable_and_hidden_by_default(): void
    {
        $this->template([
            'name' => 'Toggleable marker',
            'body' => 'BODY_COLUMN_MARKER_98765',
        ]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ListWhatsAppTemplates::class)
            ->assertTableColumnExists('body')
            ->assertDontSee('BODY_COLUMN_MARKER_98765');

        $component->toggleAllTableColumns()
            ->assertSee('BODY_COLUMN_MARKER_98765');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function template(array $overrides = []): WhatsAppTemplate
    {
        return WhatsAppTemplate::create(array_merge([
            'name' => 'Template '.fake()->unique()->numerify('###'),
            'type' => 'marketing',
            'body' => 'Hello',
        ], $overrides));
    }
}
