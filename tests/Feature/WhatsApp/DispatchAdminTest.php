<?php

namespace Tests\Feature\WhatsApp;

use App\Filament\Resources\WhatsAppDispatches\Pages\ListWhatsAppDispatches;
use App\Models\User;
use App\Models\WhatsAppDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_tracking_is_admin_only_and_unknown_has_no_retry_action(): void
    {
        $this->get('/admin/whatsapp-dispatches')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create());
        $dispatch = WhatsAppDispatch::create([
            'dedupe_key' => 'test-owner', 'kind' => 'order_owner', 'recipient_phone' => '201001234567',
            'body' => 'Owner data', 'status' => 'unknown', 'attempts' => 1,
        ]);
        Livewire::test(ListWhatsAppDispatches::class)->assertCanSeeTableRecords([$dispatch])
            ->assertTableActionHidden('retry', $dispatch);
    }
}
