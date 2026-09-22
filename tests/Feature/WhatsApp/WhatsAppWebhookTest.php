<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    private const SECRET = 'webhook-secret';

    private const URI = '/webhooks/whatsapp';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.webhook.secret', self::SECRET);
        config()->set('whatsapp.webhook.tolerance', 300);

        Cache::flush();
    }

    public function test_valid_signed_webhook_is_accepted_without_a_csrf_token(): void
    {
        $response = $this->sendSigned($this->messageEvent());

        $response->assertStatus(202);
        $response->assertJson(['status' => 'accepted']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $body = json_encode($this->messageEvent());

        $response = $this->call('POST', self::URI, [], [], [], $this->serverHeaders('wrong-signature'), $body);

        $response->assertStatus(401);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $body = json_encode($this->messageEvent());

        $response = $this->call('POST', self::URI, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);

        $response->assertStatus(401);
    }

    public function test_disallowed_hmac_algorithm_is_rejected(): void
    {
        $body = json_encode($this->messageEvent());
        $headers = $this->serverHeaders(hash_hmac('md5', $body, self::SECRET));
        $headers['HTTP_X_WEBHOOK_HMAC_ALGORITHM'] = 'md5';

        $this->call('POST', self::URI, [], [], [], $headers, $body)->assertStatus(401);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $body = json_encode($this->messageEvent());
        $headers = $this->serverHeaders(hash_hmac('sha512', $body, self::SECRET));
        $headers['HTTP_X_WEBHOOK_TIMESTAMP'] = (string) ((time() - 10000) * 1000);

        $this->call('POST', self::URI, [], [], [], $headers, $body)->assertStatus(401);
    }

    public function test_missing_timestamp_is_rejected(): void
    {
        $body = json_encode($this->messageEvent());
        $headers = $this->serverHeaders(hash_hmac('sha512', $body, self::SECRET));
        unset($headers['HTTP_X_WEBHOOK_TIMESTAMP']);

        $this->call('POST', self::URI, [], [], [], $headers, $body)->assertStatus(401);
    }

    public function test_replayed_webhook_is_deduplicated(): void
    {
        $event = $this->messageEvent();

        $this->sendSigned($event)->assertStatus(202);
        $this->sendSigned($event)->assertStatus(200)->assertJson(['status' => 'duplicate']);
    }

    public function test_distinct_events_are_both_accepted(): void
    {
        $this->sendSigned($this->messageEvent('evt_1'))->assertStatus(202);
        $this->sendSigned($this->messageEvent('evt_2'))->assertStatus(202);
    }

    public function test_ack_and_session_status_events_are_accepted(): void
    {
        $this->sendSigned([
            'id' => 'evt_ack',
            'event' => 'message.ack',
            'session' => 'default',
            'payload' => ['id' => 'true_111@c.us_ABC', 'ackName' => 'READ', 'ack' => 3],
        ])->assertStatus(202);

        $this->sendSigned([
            'id' => 'evt_status',
            'event' => 'session.status',
            'session' => 'default',
            'payload' => ['status' => 'WORKING'],
        ])->assertStatus(202);
    }

    public function test_unknown_event_shape_is_accepted_and_ignored(): void
    {
        $this->sendSigned([
            'id' => 'evt_unknown',
            'event' => 'something.new',
            'session' => 'default',
            'payload' => ['weird' => true],
        ])->assertStatus(202);
    }

    public function test_endpoint_fails_closed_when_secret_is_not_configured(): void
    {
        config()->set('whatsapp.webhook.secret', '');

        $this->sendSigned($this->messageEvent())->assertStatus(503);
    }

    private function sendSigned(array $payload)
    {
        $body = json_encode($payload);

        return $this->call(
            'POST',
            self::URI,
            [],
            [],
            [],
            $this->serverHeaders(hash_hmac('sha512', $body, self::SECRET)),
            $body,
        );
    }

    private function serverHeaders(string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_HMAC' => $signature,
            'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (time() * 1000),
            'HTTP_X_WEBHOOK_REQUEST_ID' => 'req_test',
        ];
    }

    private function messageEvent(string $id = 'evt_message_1'): array
    {
        return [
            'id' => $id,
            'event' => 'message',
            'session' => 'default',
            'timestamp' => time() * 1000,
            'payload' => [
                'id' => 'false_111@c.us_ABC',
                'from' => '111@c.us',
                'fromMe' => false,
                'body' => 'مرحبا',
                'hasMedia' => false,
            ],
        ];
    }
}
