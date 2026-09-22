<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Filament\Pages\WhatsAppAccount;
use App\Models\User;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\SessionView;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\TestCase;

class WhatsAppAccountPageTest extends TestCase
{
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
        $this->get('/admin/whatsapp-account')->assertRedirect('/admin/login');
    }

    public function test_admin_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withSession(['locale' => 'en'])
            ->get('/admin/whatsapp-account')
            ->assertOk()
            ->assertSee('WAHA Service')
            ->assertSee('WhatsApp Account');
    }

    public function test_page_renders_arabic_labels_for_the_default_admin_locale(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/whatsapp-account')
            ->assertOk()
            ->assertSee('حساب واتساب')
            ->assertSee('خدمة واتساب');
    }

    public function test_connected_state_shows_identity_and_connected_actions(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING', [
            'id' => '201234567890@c.us',
            'pushName' => 'MAI',
        ]);

        $component = Livewire::test(WhatsAppAccount::class);

        $component->assertSet('rawStatus', 'WORKING')
            ->assertSee('Connected')
            ->assertSee('MAI')
            ->assertSee('201234567890')
            ->assertActionVisible('refresh')
            ->assertActionVisible('restart')
            ->assertActionVisible('stop')
            ->assertActionVisible('logout')
            ->assertActionHidden('start')
            ->assertActionHidden('createStart');

        $this->assertFalse($component->instance()->allows(SessionView::ACTION_START));
    }

    public function test_lid_identity_never_becomes_a_phone_number(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING', [
            'id' => '214457011683409@lid',
            'pushName' => 'MAI',
        ]);

        Livewire::test(WhatsAppAccount::class)
            ->assertSee('Connected')
            ->assertSee('MAI')
            ->assertDontSee('214457011683409');
    }

    public function test_qr_required_state_shows_qr_panel_without_qr_data_in_page_state(): void
    {
        $this->gateway->session = new SessionState('default', 'SCAN_QR_CODE');
        $this->gateway->qr = base64_encode('SECRET-QR-PAYLOAD');

        $component = Livewire::test(WhatsAppAccount::class);

        $component->assertSee('QR Required')
            ->assertSee('Scan to link the account')
            ->assertSee('/admin/whatsapp/qr')
            ->assertActionVisible('refreshQr')
            ->assertActionVisible('stop')
            ->assertActionHidden('restart')
            ->assertDontSee('SECRET-QR-PAYLOAD');
    }

    public function test_stopped_state_allows_start_and_start_invokes_the_gateway(): void
    {
        $this->gateway->session = new SessionState('default', 'STOPPED');

        $component = Livewire::test(WhatsAppAccount::class);

        $component->assertSee('Stopped')
            ->assertActionVisible('start')
            ->assertActionHidden('stop')
            ->assertActionHidden('logout');

        $component->callAction('start');

        $this->assertContains('start', $this->gateway->calls);
    }

    public function test_failed_state_shows_attention_and_recovery_actions(): void
    {
        $this->gateway->session = new SessionState('default', 'FAILED');

        Livewire::test(WhatsAppAccount::class)
            ->assertSee('Attention Required')
            ->assertActionVisible('restart')
            ->assertActionVisible('logout')
            ->assertActionHidden('start');
    }

    public function test_not_created_state_creates_only_the_configured_session(): void
    {
        $this->gateway->session = null;

        $component = Livewire::test(WhatsAppAccount::class);

        $component->assertSee('Not Set Up')
            ->assertActionVisible('createStart');

        $component->callAction('createStart');

        $this->assertContains('create', $this->gateway->calls);
        $this->assertContains('start', $this->gateway->calls);
    }

    public function test_unhealthy_service_is_shown_separately_from_account_state(): void
    {
        $this->gateway->healthy = false;
        $this->gateway->session = new SessionState('default', 'WORKING');

        Livewire::test(WhatsAppAccount::class)
            ->assertSee('Unavailable')
            ->assertSee('Service Unavailable')
            ->assertActionVisible('refresh')
            ->assertActionHidden('start')
            ->assertActionHidden('restart')
            ->assertActionHidden('logout');
    }

    public function test_disabled_integration_makes_no_provider_calls_and_hides_actions(): void
    {
        $this->gateway->enabled = false;
        $this->gateway->session = new SessionState('default', 'WORKING');

        $component = Livewire::test(WhatsAppAccount::class);

        $component->assertSee('Disabled')
            ->assertActionHidden('start')
            ->assertActionHidden('restart')
            ->assertActionHidden('logout');

        $this->assertSame([], $this->gateway->calls);

        $component->call('perform', SessionView::ACTION_START);

        $this->assertSame([], $this->gateway->calls);
        $component->assertNotified(__('admin.whatsapp.notifications.disabled'));
    }

    public function test_stop_invokes_the_gateway_when_connected(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING');

        Livewire::test(WhatsAppAccount::class)->callAction('stop');

        $this->assertContains('stop', $this->gateway->calls);
    }

    public function test_restart_invokes_the_gateway(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING');

        Livewire::test(WhatsAppAccount::class)->callAction('restart');

        $this->assertContains('restart', $this->gateway->calls);
    }

    public function test_logout_requires_confirmation_before_it_runs(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING');

        $component = Livewire::test(WhatsAppAccount::class);

        $component->mountAction('logout');

        $this->assertNotContains('logout', $this->gateway->calls);

        $component->assertMountedActionModalSee(__('admin.whatsapp.confirm.logout_heading'));
        $component->assertMountedActionModalSee(__('admin.whatsapp.confirm.logout_description'));

        $component->callMountedAction();

        $this->assertContains('logout', $this->gateway->calls);
    }

    public function test_action_is_blocked_when_the_live_state_changed_before_it_ran(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING');

        $component = Livewire::test(WhatsAppAccount::class);

        // The provider state changes after the page rendered, before the action.
        $this->gateway->session = new SessionState('default', 'STOPPED');

        $component->call('perform', SessionView::ACTION_STOP);

        $this->assertNotContains('stop', $this->gateway->calls);
        $component->assertNotified(__('admin.whatsapp.notifications.action_failed'));
    }

    public function test_provider_action_failure_shows_a_sanitized_notification(): void
    {
        $this->gateway->session = new SessionState('default', 'WORKING');
        $this->gateway->throwOnAction = WhatsAppException::requestFailed('stop session', 500);

        $component = Livewire::test(WhatsAppAccount::class);

        $component->call('perform', SessionView::ACTION_STOP);

        $component->assertNotified(__('admin.whatsapp.notifications.action_failed'));
        $component->assertDontSee('HTTP 500');
        $component->assertDontSee('stop session');
    }
}
