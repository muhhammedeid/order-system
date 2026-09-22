<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMarketingStatus;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Livewire\OrderWhatsAppPanel;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class CustomerMarketingConsentTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    public function test_new_customers_default_to_unknown_and_are_not_eligible(): void
    {
        $customer = Customer::factory()->create();

        $this->assertSame(WhatsAppMarketingStatus::Unknown, $customer->whatsapp_marketing_status);
        $this->assertNull($customer->whatsapp_marketing_opted_in_at);
        $this->assertNull($customer->whatsapp_marketing_opted_out_at);
        $this->assertFalse($customer->canReceiveWhatsAppMarketing());

        $matched = Customer::matchOrCreate(['name' => 'Checkout Customer', 'phone' => '01009999999']);

        $this->assertSame(WhatsAppMarketingStatus::Unknown, $matched->whatsapp_marketing_status);
        $this->assertFalse($matched->canReceiveWhatsAppMarketing());
    }

    public function test_subscribed_customer_with_a_usable_number_is_eligible(): void
    {
        $customer = Customer::factory()->create([
            'whatsapp_marketing_status' => 'subscribed',
            'whatsapp' => null,
            'phone' => '0112347663',
        ]);

        $this->assertTrue($customer->canReceiveWhatsAppMarketing());
    }

    public function test_subscribed_customer_prefers_the_whatsapp_number_without_falling_back(): void
    {
        $withWhatsApp = Customer::factory()->create([
            'whatsapp_marketing_status' => 'subscribed',
            'whatsapp' => '20112347663',
            'phone' => '0112347663',
        ]);

        $this->assertTrue($withWhatsApp->canReceiveWhatsAppMarketing());

        $brokenWhatsApp = Customer::factory()->create([
            'whatsapp_marketing_status' => 'subscribed',
            'whatsapp' => '123',
            'phone' => '0112347663',
        ]);

        $this->assertFalse($brokenWhatsApp->canReceiveWhatsAppMarketing());
    }

    public function test_subscribed_customer_without_a_usable_number_is_not_eligible(): void
    {
        $noNumber = Customer::factory()->create([
            'whatsapp_marketing_status' => 'subscribed',
            'whatsapp' => null,
            'phone' => '',
        ]);

        $this->assertFalse($noNumber->canReceiveWhatsAppMarketing());

        $shortNumber = Customer::factory()->create([
            'whatsapp_marketing_status' => 'subscribed',
            'phone' => '12345',
        ]);

        $this->assertFalse($shortNumber->canReceiveWhatsAppMarketing());
    }

    public function test_unsubscribed_customers_are_never_eligible(): void
    {
        $customer = Customer::factory()->create([
            'whatsapp_marketing_status' => 'unsubscribed',
            'phone' => '0112347663',
        ]);

        $this->assertFalse($customer->canReceiveWhatsAppMarketing());
    }

    public function test_opt_in_sets_the_timestamp_and_preserves_older_opt_out_history(): void
    {
        $customer = Customer::factory()->create();
        $historicOptOut = now()->subDays(10)->startOfSecond();

        $customer->forceFill(['whatsapp_marketing_opted_out_at' => $historicOptOut])->save();
        $customer->update(['whatsapp_marketing_status' => 'subscribed']);
        $customer->refresh();

        $this->assertNotNull($customer->whatsapp_marketing_opted_in_at);
        $this->assertNotNull($customer->whatsapp_marketing_opted_out_at);
        $this->assertTrue($customer->whatsapp_marketing_opted_out_at->equalTo($historicOptOut));
    }

    public function test_opt_out_preserves_the_opt_in_timestamp(): void
    {
        $customer = Customer::factory()->create(['whatsapp_marketing_status' => 'subscribed']);
        $optedInAt = $customer->whatsapp_marketing_opted_in_at;

        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);
        $customer->refresh();

        $this->assertNotNull($customer->whatsapp_marketing_opted_out_at);
        $this->assertNotNull($optedInAt);
        $this->assertTrue($customer->whatsapp_marketing_opted_in_at->equalTo($optedInAt));
    }

    public function test_unknown_to_unsubscribed_records_the_opt_out(): void
    {
        $customer = Customer::factory()->create();

        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);
        $customer->refresh();

        $this->assertNotNull($customer->whatsapp_marketing_opted_out_at);
        $this->assertNull($customer->whatsapp_marketing_opted_in_at);
    }

    public function test_re_subscription_sets_a_new_opt_in_and_preserves_the_previous_opt_out(): void
    {
        $customer = Customer::factory()->create(['whatsapp_marketing_status' => 'subscribed']);
        $firstOptIn = $customer->whatsapp_marketing_opted_in_at;

        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);
        $optedOutAt = $customer->refresh()->whatsapp_marketing_opted_out_at;

        $this->travel(5)->seconds();

        $customer->update(['whatsapp_marketing_status' => 'subscribed']);
        $customer->refresh();

        $this->assertNotNull($optedOutAt);
        $this->assertTrue($customer->whatsapp_marketing_opted_out_at->equalTo($optedOutAt));
        $this->assertTrue($customer->whatsapp_marketing_opted_in_at->greaterThan($firstOptIn));
    }

    public function test_resetting_to_unknown_clears_both_timestamps(): void
    {
        $customer = Customer::factory()->create(['whatsapp_marketing_status' => 'subscribed']);
        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);

        $customer->update(['whatsapp_marketing_status' => 'unknown']);
        $customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Unknown, $customer->whatsapp_marketing_status);
        $this->assertNull($customer->whatsapp_marketing_opted_in_at);
        $this->assertNull($customer->whatsapp_marketing_opted_out_at);
        $this->assertFalse($customer->canReceiveWhatsAppMarketing());
    }

    public function test_saving_the_same_status_again_never_alters_timestamps(): void
    {
        $customer = Customer::factory()->create(['whatsapp_marketing_status' => 'subscribed']);
        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);

        $optedInAt = $customer->whatsapp_marketing_opted_in_at;
        $optedOutAt = $customer->whatsapp_marketing_opted_out_at;

        $customer->update(['name' => 'Renamed Store']);
        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);
        $customer->refresh();

        $this->assertTrue($customer->whatsapp_marketing_opted_in_at->equalTo($optedInAt));
        $this->assertTrue($customer->whatsapp_marketing_opted_out_at->equalTo($optedOutAt));
    }

    public function test_admin_pages_render_with_the_marketing_section_and_badge(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create(['whatsapp_marketing_status' => 'subscribed']);

        $this->actingAs($admin)->get('/admin/customers')->assertOk();
        $this->actingAs($admin)->get('/admin/customers/create')->assertOk();
        $this->actingAs($admin)->get("/admin/customers/{$customer->getKey()}/edit")->assertOk();
    }

    public function test_admin_edits_record_consent_timestamps_through_the_customer_form(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditCustomer::class, ['record' => $customer->getKey()])
            ->fillForm(['whatsapp_marketing_status' => 'subscribed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Subscribed, $customer->whatsapp_marketing_status);
        $this->assertNotNull($customer->whatsapp_marketing_opted_in_at);

        Livewire::actingAs($admin)
            ->test(EditCustomer::class, ['record' => $customer->getKey()])
            ->fillForm(['whatsapp_marketing_status' => 'unsubscribed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Unsubscribed, $customer->whatsapp_marketing_status);
        $this->assertNotNull($customer->whatsapp_marketing_opted_out_at);
    }

    public function test_operational_order_messages_still_work_for_unsubscribed_customers(): void
    {
        $gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $gateway);

        $order = $this->createOrderWithCustomer([
            'whatsapp' => '0112347663',
            'whatsapp_marketing_status' => 'unsubscribed',
        ]);

        $conversation = WhatsAppConversation::create([
            'customer_id' => $order->customer_id,
            'provider_chat_id' => '20112347663@c.us',
        ]);

        Livewire::test(OrderWhatsAppPanel::class, ['order' => $order->id])
            ->set('messageBody', 'Operational order update')
            ->call('sendCustomMessage')
            ->assertHasNoErrors();

        $message = WhatsAppMessage::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('Operational order update', $message->body);
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertContains('send_text', $gateway->calls);

        $order->customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Unsubscribed, $order->customer->whatsapp_marketing_status);
        $this->assertFalse($order->customer->canReceiveWhatsAppMarketing());
    }
}
