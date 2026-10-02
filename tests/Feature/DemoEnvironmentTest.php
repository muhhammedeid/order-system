<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Filament\Auth\DemoLogin;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\WhatsApp\UnavailableGateway;
use App\Services\WhatsApp\WahaGateway;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class DemoEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_username_authenticates_using_the_existing_password_hash(): void
    {
        config(['demo.enabled' => true]);
        $admin = User::factory()->create([
            'email' => config('demo.admin_email'),
            'password' => Hash::make('Demo-test-password'),
        ]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DemoLogin::class)
            ->fillForm(['email' => 'admin', 'password' => 'Demo-test-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_demo_rejects_email_login_and_an_incorrect_password(): void
    {
        config(['demo.enabled' => true]);
        User::factory()->create([
            'email' => config('demo.admin_email'),
            'password' => Hash::make('Demo-test-password'),
        ]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ([config('demo.admin_email') => 'Demo-test-password', 'another-user' => 'Demo-test-password', 'admin' => 'incorrect'] as $username => $password) {
            Livewire::test(DemoLogin::class)
                ->fillForm(['email' => $username, 'password' => $password])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
            $this->assertGuest();
        }
    }

    public function test_normal_deployments_use_the_username_and_email_login(): void
    {
        config(['demo.enabled' => false]);
        $provider = new AdminPanelProvider($this->app);
        $this->assertSame(Login::class, $provider->panel(Panel::make())->getLoginRouteAction());
    }

    public function test_demo_blocks_provider_access_even_if_whatsapp_is_configured(): void
    {
        config([
            'demo.enabled' => true,
            'whatsapp.enabled' => true,
            'whatsapp.driver' => 'waha',
            'whatsapp.base_url' => 'https://provider.example',
            'whatsapp.api_key' => 'test-key',
        ]);
        Http::fake();

        $gateway = app(WhatsAppGateway::class);
        $this->assertInstanceOf(UnavailableGateway::class, $gateway);
        $this->assertFalse($gateway->enabled());
        $this->assertFalse($gateway->health());
        $this->assertFalse(app(WahaGateway::class)->enabled());
        $this->assertFalse(app(WahaGateway::class)->health());
        Http::assertNothingSent();
    }

    public function test_storefront_shares_only_the_demo_flag(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page
            ->where('demo', true)
            ->missing('demo_admin_email')
            ->missing('demo_admin_password'));
    }

    public function test_demo_rejects_inbound_provider_webhooks(): void
    {
        config(['demo.enabled' => true, 'whatsapp.webhook.secret' => 'test-secret']);

        $this->postJson('/webhooks/whatsapp', ['event' => 'message'])
            ->assertStatus(503);
    }
}
