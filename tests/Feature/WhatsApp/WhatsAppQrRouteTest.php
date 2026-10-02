<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Models\User;
use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\TestCase;

class WhatsAppQrRouteTest extends TestCase
{
    use RefreshDatabase;

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get('/admin/whatsapp/qr')->assertRedirect('/admin/login');
    }

    public function test_admin_receives_a_non_cached_png(): void
    {
        $this->actingAs(User::factory()->create());
        $this->gateway->session = new SessionState('default', 'SCAN_QR_CODE');
        $this->gateway->qr = base64_encode('PNG-BYTES');

        $response = $this->get('/admin/whatsapp/qr');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('PNG-BYTES', $response->getContent());
    }

    public function test_qr_is_unavailable_when_the_provider_has_none(): void
    {
        $this->actingAs(User::factory()->create());
        $this->gateway->session = new SessionState('default', 'WORKING');
        $this->gateway->qr = null;

        $this->get('/admin/whatsapp/qr')->assertNotFound();
    }

    public function test_qr_route_is_unavailable_when_the_integration_is_disabled(): void
    {
        $this->actingAs(User::factory()->create());
        $this->gateway->enabled = false;

        $this->get('/admin/whatsapp/qr')->assertNotFound();

        $this->assertSame([], $this->gateway->calls);
    }

    public function test_qr_provider_failure_returns_not_found_without_leaking_details(): void
    {
        $this->actingAs(User::factory()->create());
        $this->gateway->throwOnQr = WhatsAppException::requestFailed('qr', 500);

        $this->get('/admin/whatsapp/qr')->assertNotFound();
    }

    public function test_qr_serves_only_the_configured_session(): void
    {
        $this->actingAs(User::factory()->create());
        config()->set('whatsapp.session', 'configured-session');
        $this->gateway->qr = base64_encode('PNG');

        $this->get('/admin/whatsapp/qr?session=other-session')->assertOk();

        $this->assertSame('configured-session', $this->gateway->lastQrSession);
    }
}
