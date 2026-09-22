<?php

namespace Tests\Unit\WhatsApp;

use App\Support\WhatsApp\WebhookEvent;
use PHPUnit\Framework\TestCase;

class WebhookEventTest extends TestCase
{
    public function test_it_prefers_the_envelope_id_for_idempotency(): void
    {
        $event = WebhookEvent::fromRequest(
            ['id' => 'evt_123', 'event' => 'message', 'session' => 'default', 'payload' => []],
            'req_999',
            1700000000000,
        );

        $this->assertSame('id:evt_123', $event->idempotencyKey());
    }

    public function test_it_falls_back_to_request_id_then_body_hash(): void
    {
        $event = WebhookEvent::fromRequest(
            ['event' => 'message', 'session' => 'default', 'payload' => []],
            'req_999',
            null,
        );
        $this->assertSame('req:req_999', $event->idempotencyKey());

        $body = ['event' => 'message', 'session' => 'default', 'payload' => ['body' => 'x']];
        $event = WebhookEvent::fromRequest($body, null, null);
        $this->assertSame('body:'.hash('sha256', $event->raw), $event->idempotencyKey());
    }

    public function test_it_extracts_message_id_and_ack_name(): void
    {
        $event = WebhookEvent::fromRequest([
            'id' => 'evt_ack',
            'event' => 'message.ack',
            'session' => 'default',
            'payload' => ['id' => 'true_111@c.us_ABC', 'ackName' => 'READ'],
        ], null, null);

        $this->assertSame('true_111@c.us_ABC', $event->messageId());
        $this->assertSame('READ', $event->ackName());
    }

    public function test_it_keeps_the_session_status_payload(): void
    {
        $event = WebhookEvent::fromRequest([
            'id' => 'evt_status',
            'event' => 'session.status',
            'session' => 'default',
            'payload' => ['status' => 'WORKING'],
        ], null, null);

        $this->assertSame('session.status', $event->event);
        $this->assertSame('WORKING', $event->payload['status']);
    }
}
