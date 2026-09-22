<?php

namespace Tests\Unit\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsApp\WahaGateway;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WahaGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.enabled', true);
        config()->set('whatsapp.provider', 'waha');
        config()->set('whatsapp.base_url', 'http://waha.test');
        config()->set('whatsapp.api_key', 'test-api-key');
        config()->set('whatsapp.session', 'default');
        config()->set('whatsapp.timeout', 5);
        config()->set('whatsapp.verify_ssl', false);
        config()->set('whatsapp.webhook.secret', 'test-hmac-secret');
    }

    public function test_container_resolves_the_interface_to_waha(): void
    {
        $this->assertInstanceOf(WahaGateway::class, app(WhatsAppGateway::class));
    }

    public function test_health_is_true_when_provider_responds_ok(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->assertTrue(app(WhatsAppGateway::class)->health());
    }

    public function test_health_is_false_when_disabled(): void
    {
        config()->set('whatsapp.enabled', false);
        Http::fake();

        $this->assertFalse(app(WhatsAppGateway::class)->health());
        Http::assertNothingSent();
    }

    public function test_send_text_posts_session_chat_and_text_with_api_key(): void
    {
        Http::fake(['*' => Http::response(['id' => 'true_111@c.us_ABC', 'timestamp' => 1700000000], 201)]);

        $message = app(WhatsAppGateway::class)->sendText('111@c.us', 'مرحبا');

        $this->assertSame('true_111@c.us_ABC', $message->providerId);
        $this->assertSame(1700000000, $message->timestamp);

        Http::assertSent(fn ($request) => $request->url() === 'http://waha.test/api/sendText'
            && $request['session'] === 'default'
            && $request['chatId'] === '111@c.us'
            && $request['text'] === 'مرحبا'
            && $request->hasHeader('X-Api-Key', 'test-api-key'));
    }

    public function test_send_text_reads_the_noweb_key_id_shape(): void
    {
        Http::fake(['*' => Http::response([
            'key' => ['remoteJid' => '111@s.whatsapp.net', 'fromMe' => true, 'id' => '3EB0ABC'],
            'messageTimestamp' => '1790068606',
            'status' => 'PENDING',
        ], 201)]);

        $message = app(WhatsAppGateway::class)->sendText('111@c.us', 'hi');

        $this->assertSame('3EB0ABC', $message->providerId);
        $this->assertSame(1790068606, $message->timestamp);
    }

    public function test_send_image_uses_send_image_endpoint(): void
    {
        Http::fake(['*' => Http::response(['id' => 'true_111@c.us_IMG'], 201)]);

        app(WhatsAppGateway::class)->sendMedia('111@c.us', [
            'mimetype' => 'image/jpeg',
            'url' => 'https://example.test/a.jpg',
            'filename' => 'a.jpg',
        ], 'caption');

        Http::assertSent(fn ($request) => $request->url() === 'http://waha.test/api/sendImage'
            && $request['file']['mimetype'] === 'image/jpeg'
            && $request['caption'] === 'caption');
    }

    public function test_send_document_uses_send_file_endpoint(): void
    {
        Http::fake(['*' => Http::response(['id' => 'true_111@c.us_DOC'], 201)]);

        app(WhatsAppGateway::class)->sendMedia('111@c.us', [
            'mimetype' => 'application/pdf',
            'url' => 'https://example.test/a.pdf',
            'filename' => 'a.pdf',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'http://waha.test/api/sendFile');
    }

    public function test_session_returns_null_when_provider_returns_404(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $this->assertNull(app(WhatsAppGateway::class)->session('default'));
    }

    public function test_create_session_sends_webhooks_when_provided(): void
    {
        Http::fake(['*' => Http::response(['name' => 'default', 'status' => 'STARTING'], 201)]);

        $state = app(WhatsAppGateway::class)->createSession('default', [
            ['url' => 'https://app.test/webhooks/whatsapp', 'events' => ['message', 'message.ack', 'session.status']],
        ]);

        $this->assertSame('STARTING', $state->status);

        Http::assertSent(fn ($request) => $request->url() === 'http://waha.test/api/sessions'
            && $request['config']['webhooks'][0]['events'] === ['message', 'message.ack', 'session.status']
            && $request['config']['webhooks'][0]['hmac']['key'] === 'test-hmac-secret');
    }

    public function test_create_session_keeps_an_explicit_hmac_override(): void
    {
        Http::fake(['*' => Http::response(['name' => 'default', 'status' => 'STARTING'], 201)]);

        app(WhatsAppGateway::class)->createSession('default', [
            [
                'url' => 'https://app.test/webhooks/whatsapp',
                'events' => ['message'],
                'hmac' => ['key' => 'override-secret'],
            ],
        ]);

        Http::assertSent(fn ($request) => $request['config']['webhooks'][0]['hmac']['key'] === 'override-secret');
    }

    public function test_outbound_calls_fail_closed_when_not_configured(): void
    {
        config()->set('whatsapp.api_key', null);
        Http::fake();

        $this->expectException(WhatsAppException::class);

        app(WhatsAppGateway::class)->sendText('111@c.us', 'hi');
    }

    public function test_provider_failure_raises_a_sanitized_exception(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        try {
            app(WhatsAppGateway::class)->sendText('111@c.us', 'hi');
            $this->fail('Expected WhatsAppException was not thrown.');
        } catch (WhatsAppException $exception) {
            $this->assertStringNotContainsString('test-api-key', $exception->getMessage());
            $this->assertStringContainsString('HTTP 401', $exception->getMessage());
        }
    }

    public function test_qr_uses_get_and_returns_base64_data(): void
    {
        Http::fake(['*' => Http::response(['mimetype' => 'image/png', 'data' => 'QUJD'], 200)]);

        $qr = app(WhatsAppGateway::class)->qr('default');

        $this->assertSame('QUJD', $qr);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'http://waha.test/api/default/auth/qr');
    }

    public function test_qr_returns_null_when_no_qr_is_available(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $this->assertNull(app(WhatsAppGateway::class)->qr('default'));
    }

    public function test_health_is_false_when_provider_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->assertFalse(app(WhatsAppGateway::class)->health());
    }

    public function test_send_timeout_is_wrapped_as_a_sanitized_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        try {
            app(WhatsAppGateway::class)->sendText('111@c.us', 'hi');
            $this->fail('Expected WhatsAppException was not thrown.');
        } catch (WhatsAppException $exception) {
            $this->assertStringContainsString('unreachable', $exception->getMessage());
        }
    }

    public function test_session_timeout_is_wrapped_as_a_sanitized_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        try {
            app(WhatsAppGateway::class)->session('default');
            $this->fail('Expected WhatsAppException was not thrown.');
        } catch (WhatsAppException $exception) {
            $this->assertStringContainsString('unreachable', $exception->getMessage());
        }
    }
}
