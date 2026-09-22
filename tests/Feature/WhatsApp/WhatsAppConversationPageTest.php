<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Filament\Resources\WhatsAppConversations\Pages\ViewWhatsAppConversation;
use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class WhatsAppConversationPageTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $conversation = $this->conversationWithMessage();

        $this->get('/admin/whatsapp-conversations')->assertRedirect('/admin/login');
        $this->get("/admin/whatsapp-conversations/{$conversation->id}")->assertRedirect('/admin/login');
    }

    public function test_admin_can_open_the_inbox_list(): void
    {
        $this->actingAs(User::factory()->create());
        $this->conversationWithMessage();

        $this->withSession(['locale' => 'en'])
            ->get('/admin/whatsapp-conversations')
            ->assertOk()
            ->assertSee('Hello there');
    }

    public function test_admin_can_open_the_conversation_and_sees_history(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();

        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->assertSee('Hello there')
            ->assertSee('20112347663@c.us');
    }

    public function test_message_bodies_are_rendered_escaped(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage('<script>XSS_MARKER_12345</script>');

        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->assertSee('&lt;script&gt;XSS_MARKER_12345&lt;/script&gt;', false)
            ->assertDontSee('<script>XSS_MARKER_12345', false);
    }

    public function test_sending_a_reply_persists_and_clears_the_composer(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();

        $component = Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->set('replyBody', '  Reply from the panel  ')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('replyBody', '');

        $message = $conversation->messages()
            ->where('direction', WhatsAppMessageDirection::Outbound)
            ->firstOrFail();

        $this->assertSame('Reply from the panel', $message->body);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('SENT-1', $message->provider_message_id);
        $this->assertContains('send_text', $this->gateway->calls);

        $conversation->refresh();
        $this->assertSame('Reply from the panel', $conversation->last_message_preview);
        $this->assertSame('outbound', $conversation->last_message_direction);
    }

    public function test_page_render_never_sends_a_message(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();

        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id]);

        $this->assertNotContains('send_text', $this->gateway->calls);
    }

    public function test_failed_send_is_recorded_and_keeps_the_composer_text(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();

        $this->gateway->throwOnAction = WhatsAppException::requestFailed('send text', 500);

        $component = Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->set('replyBody', 'Will fail')
            ->call('sendReply')
            ->assertSet('replyBody', 'Will fail');

        $message = $conversation->messages()
            ->where('direction', WhatsAppMessageDirection::Outbound)
            ->firstOrFail();

        $this->assertSame(WhatsAppMessageStatus::Failed, $message->status);
        $this->assertNull($message->provider_message_id);
        $component->assertNotified(__('admin.whatsapp.notifications.action_failed'));
    }

    public function test_disabled_integration_blocks_sending_without_provider_calls(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();

        $this->gateway->enabled = false;

        $component = Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->set('replyBody', 'Blocked')
            ->call('sendReply');

        $this->assertSame(0, $conversation->messages()
            ->where('direction', WhatsAppMessageDirection::Outbound)
            ->count());

        $this->assertSame([], $this->gateway->calls);
        $component->assertNotified(__('admin.whatsapp.notifications.disabled'));
    }

    public function test_reply_requires_a_body(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();

        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->set('replyBody', '')
            ->call('sendReply')
            ->assertHasErrors(['replyBody' => 'required']);
    }

    public function test_customer_linking_only_writes_the_conversation(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();
        $customer = Customer::factory()->create([
            'name' => 'Original Name',
            'phone' => '01000000000',
        ]);

        $component = Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id]);

        $component->call('linkCustomer', $customer->id);

        $this->assertSame($customer->id, $conversation->refresh()->customer_id);

        $customer->refresh();
        $this->assertSame('Original Name', $customer->name);
        $this->assertSame('01000000000', $customer->phone);

        $component->call('unlinkCustomer');

        $this->assertNull($conversation->refresh()->customer_id);
    }

    public function test_opening_the_conversation_resets_unread_count(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->conversationWithMessage();
        $conversation->forceFill(['unread_count' => 3])->save();

        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id]);

        $this->assertSame(0, $conversation->refresh()->unread_count);
    }

    public function test_navigation_badge_counts_conversations_with_unread_messages(): void
    {
        $this->assertNull(WhatsAppConversationResource::getNavigationBadge());

        $this->conversationWithMessage();
        WhatsAppConversation::query()->firstOrFail()->forceFill(['unread_count' => 2])->save();

        $this->assertSame('1', WhatsAppConversationResource::getNavigationBadge());
    }

    public function test_conversation_timeline_shows_the_order_badge_only_for_order_linked_messages(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->createOrderWithCustomer();
        $conversation = $this->conversationWithMessage('General note');

        $conversation->messages()->create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => WhatsAppMessageType::Text,
            'body' => 'Order linked message',
            'occurred_at' => now(),
        ]);

        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])
            ->assertSee('Order linked message')
            ->assertSee('#'.$order->order_number)
            ->assertSee('/admin/order-management/'.$order->id);
    }

    private function conversationWithMessage(string $body = 'Hello there'): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::create([
            'provider_chat_id' => '20112347663@c.us',
            'resolved_phone' => '20112347663',
            'last_message_at' => now(),
            'last_message_preview' => $body,
            'last_message_direction' => 'inbound',
            'unread_count' => 1,
        ]);

        $conversation->messages()->create([
            'provider_message_id' => 'IN-1',
            'direction' => WhatsAppMessageDirection::Inbound,
            'message_type' => WhatsAppMessageType::Text,
            'body' => $body,
            'occurred_at' => now(),
        ]);

        return $conversation;
    }
}
