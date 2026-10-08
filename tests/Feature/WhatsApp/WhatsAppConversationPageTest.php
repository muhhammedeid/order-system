<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Filament\Resources\WhatsAppConversations\Pages\ListWhatsAppConversations;
use App\Filament\Resources\WhatsAppConversations\Pages\ViewWhatsAppConversation;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\TestCase;

class WhatsAppConversationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $conversation = $this->privateConversation();
        $this->get('/admin/whatsapp-conversations')->assertRedirect('/admin/login');
        $this->get("/admin/whatsapp-conversations/{$conversation->id}")->assertRedirect('/admin/login');
    }

    public function test_admin_cannot_open_private_inbox_or_conversations(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->privateConversation();
        $this->get('/admin')->assertOk()->assertDontSee('/admin/whatsapp-conversations');
        $this->get('/admin/whatsapp-conversations')->assertForbidden()->assertDontSee('PRIVATE_MESSAGE');
        $this->get("/admin/whatsapp-conversations/{$conversation->id}")
            ->assertForbidden()->assertDontSee('PRIVATE_MESSAGE');
        $this->assertSame(3, $conversation->refresh()->unread_count);
    }

    public function test_livewire_cannot_open_private_inbox_or_mutate_conversations(): void
    {
        $this->actingAs(User::factory()->create());
        $conversation = $this->privateConversation();
        $gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $gateway);
        Livewire::test(ListWhatsAppConversations::class)->assertForbidden();
        Livewire::test(ViewWhatsAppConversation::class, ['record' => $conversation->id])->assertForbidden();
        $this->assertSame(3, $conversation->refresh()->unread_count);
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame([], $gateway->calls);
    }

    private function privateConversation(): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::create([
            'provider_chat_id' => '20112347663@c.us',
            'resolved_phone' => '20112347663',
            'last_message_at' => now(),
            'last_message_preview' => 'PRIVATE_MESSAGE',
            'last_message_direction' => 'inbound',
            'unread_count' => 3,
        ]);
        $conversation->messages()->create([
            'provider_message_id' => 'IN-1',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'PRIVATE_MESSAGE',
            'occurred_at' => now(),
        ]);

        return $conversation;
    }
}
