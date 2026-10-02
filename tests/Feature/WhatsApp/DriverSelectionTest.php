<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsApp\UnavailableGateway;
use App\Services\WhatsApp\WahaGateway;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriverSelectionTest extends TestCase
{
    public function test_future_and_unknown_drivers_fail_closed_without_waha_fallback(): void
    {
        Http::fake();
        foreach (['future_transport', 'invalid'] as $driver) {
            config(['whatsapp.driver' => $driver, 'whatsapp.enabled' => true]);
            $gateway = app(WhatsAppGateway::class);
            $this->assertInstanceOf(UnavailableGateway::class, $gateway);
            $this->assertFalse($gateway->enabled());
            try {
                $gateway->sendText('recipient', 'private message');
                $this->fail('Unavailable driver must not send.');
            } catch (WhatsAppException) {
                Http::assertNothingSent();
            }
        }
    }

    public function test_waha_is_selectable_and_legacy_configuration_remains_supported(): void
    {
        config(['whatsapp.driver' => 'waha']);
        $this->assertInstanceOf(WahaGateway::class, app(WhatsAppGateway::class));
        config(['whatsapp.driver' => null, 'whatsapp.provider' => 'waha']);
        $this->assertInstanceOf(WahaGateway::class, app(WhatsAppGateway::class));
    }
}
