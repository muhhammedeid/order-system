<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Customer;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\TestCase;

class InboxIngestionTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.webhook.secret', self::SECRET);
        config()->set('whatsapp.webhook.tolerance', 300);

        Cache::flush();

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_inbound_message_creates_conversation_and_message(): void
    {
        $this->postEnvelope($this->messageEnvelope('message', '214457011683409@lid', 'MSG1'))
            ->assertStatus(202);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertSame('214457011683409@lid', $conversation->provider_chat_id);
        $this->assertNull($conversation->customer_id);
        $this->assertNull($conversation->resolved_phone);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('Hello', $conversation->last_message_preview);
        $this->assertSame('inbound', $conversation->last_message_direction);
        $this->assertNotNull($conversation->last_message_at);

        $message = $conversation->messages()->firstOrFail();

        $this->assertSame(WhatsAppMessageDirection::Inbound, $message->direction);
        $this->assertSame('text', $message->message_type->value);
        $this->assertSame('Hello', $message->body);
        $this->assertNull($message->status);
        $this->assertSame('MSG1', $message->provider_message_id);
    }

    public function test_second_inbound_message_reuses_the_conversation(): void
    {
        $this->postEnvelope($this->messageEnvelope('message', '214457011683409@lid', 'MSG1'))->assertStatus(202);
        $this->postEnvelope($this->messageEnvelope('message', '214457011683409@lid', 'MSG2', ['body' => 'Second']))->assertStatus(202);

        $this->assertSame(1, WhatsAppConversation::count());

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(2, $conversation->unread_count);
        $this->assertSame('Second', $conversation->last_message_preview);
    }

    public function test_replayed_envelope_is_reported_as_duplicate(): void
    {
        $envelope = $this->messageEnvelope('message', '214457011683409@lid', 'MSG1');

        $this->postEnvelope($envelope)->assertStatus(202);
        $this->postEnvelope($envelope)->assertStatus(200)->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WhatsAppMessage::count());
        $this->assertSame(1, WhatsAppConversation::query()->firstOrFail()->unread_count);
    }

    public function test_same_message_token_with_a_new_envelope_does_not_duplicate(): void
    {
        $first = $this->messageEnvelope('message', '214457011683409@lid', 'SAME');
        $second = $this->messageEnvelope('message', '214457011683409@lid', 'SAME');

        $this->postEnvelope($first)->assertStatus(202);
        $this->postEnvelope($second)->assertStatus(202);

        $this->assertSame(1, WhatsAppMessage::count());
        $this->assertSame(1, WhatsAppConversation::query()->firstOrFail()->unread_count);
    }

    public function test_same_message_token_in_a_different_conversation_is_allowed(): void
    {
        $this->postEnvelope($this->messageEnvelope('message', '111@c.us', 'TOKEN'))->assertStatus(202);
        $this->postEnvelope($this->messageEnvelope('message', '222@c.us', 'TOKEN'))->assertStatus(202);

        $this->assertSame(2, WhatsAppConversation::count());
        $this->assertSame(2, WhatsAppMessage::count());
    }

    public function test_c_us_chat_links_exactly_one_existing_customer(): void
    {
        $customer = Customer::factory()->create(['phone' => '0112347663']);
        $this->gateway->resolvedPhone = '20112347663';

        $this->postEnvelope($this->messageEnvelope('message', '20112347663@c.us', 'LINK1'))->assertStatus(202);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertSame($customer->id, $conversation->customer_id);
        $this->assertSame('20112347663', $conversation->resolved_phone);
        $this->assertSame(1, Customer::count());
    }

    public function test_ambiguous_phone_match_stays_unlinked(): void
    {
        Customer::factory()->create(['phone' => '0112347663']);
        Customer::factory()->create(['whatsapp' => '20112347663']);
        $this->gateway->resolvedPhone = '20112347663';

        $this->postEnvelope($this->messageEnvelope('message', '20112347663@c.us', 'AMB1'))->assertStatus(202);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertNull($conversation->customer_id);
        $this->assertSame('20112347663', $conversation->resolved_phone);
    }

    public function test_unresolvable_lid_conversation_stays_usable_and_unlinked(): void
    {
        $this->gateway->resolvedPhone = null;

        $this->postEnvelope($this->messageEnvelope('message', '214457011683409@lid', 'LID1'))->assertStatus(202);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertNull($conversation->customer_id);
        $this->assertNull($conversation->resolved_phone);
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_phone_sent_outbound_message_is_persisted_once(): void
    {
        $envelope = $this->messageEnvelope('message.any', '20112347663@c.us', 'OUT1', [
            'fromMe' => true,
            'to' => '20112347663@c.us',
            'source' => 'app',
            'body' => 'Sent from the phone',
        ]);

        $this->postEnvelope($envelope)->assertStatus(202);
        $this->postEnvelope($envelope)->assertStatus(200);

        $this->assertSame(1, WhatsAppMessage::count());

        $message = WhatsAppMessage::query()->firstOrFail();

        $this->assertSame(WhatsAppMessageDirection::Outbound, $message->direction);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('Sent from the phone', $message->body);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertSame(0, $conversation->unread_count);
        $this->assertSame('outbound', $conversation->last_message_direction);
    }

    public function test_api_sourced_message_any_events_are_ignored(): void
    {
        $envelope = $this->messageEnvelope('message.any', '20112347663@c.us', 'OUT2', [
            'fromMe' => true,
            'to' => '20112347663@c.us',
            'source' => 'api',
            'body' => 'Sent through the API',
        ]);

        $this->postEnvelope($envelope)->assertStatus(202);

        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_inbound_message_any_events_are_ignored(): void
    {
        $envelope = $this->messageEnvelope('message.any', '20112347663@c.us', 'OUT3', [
            'fromMe' => false,
            'source' => 'app',
        ]);

        $this->postEnvelope($envelope)->assertStatus(202);

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_from_me_message_events_are_ignored(): void
    {
        $envelope = $this->messageEnvelope('message', '20112347663@c.us', 'OWN1', [
            'fromMe' => true,
            'to' => '20112347663@c.us',
        ]);

        $this->postEnvelope($envelope)->assertStatus(202);

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_group_and_broadcast_chats_are_ignored(): void
    {
        $this->postEnvelope($this->messageEnvelope('message', '123@g.us', 'GRP1'))->assertStatus(202);
        $this->postEnvelope($this->messageEnvelope('message', 'status@broadcast', 'BRD1'))->assertStatus(202);

        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
    }

    #[DataProvider('outboundAckIds')]
    public function test_ack_updates_the_matching_outbound_message(string $providerId): void
    {
        $conversation = WhatsAppConversation::create(['provider_chat_id' => '20112347663@c.us']);
        $message = $conversation->messages()->create([
            'provider_message_id' => '3EB0ABC',
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => 'text',
            'body' => 'Reply',
            'status' => WhatsAppMessageStatus::Sent,
            'occurred_at' => now(),
        ]);

        $this->postEnvelope([
            'id' => 'evt_'.Str::random(20),
            'timestamp' => time() * 1000,
            'event' => 'message.ack',
            'session' => 'default',
            'payload' => [
                'id' => $providerId,
                'from' => '20112347663@c.us',
                'fromMe' => true,
                'ack' => 2,
                'ackName' => 'DEVICE',
            ],
        ])->assertStatus(202);

        $this->assertSame(WhatsAppMessageStatus::Delivered, $message->refresh()->status);
    }

    public static function outboundAckIds(): array
    {
        return [
            'without participant' => ['true_20112347663@c.us_3EB0ABC'],
            'with participant' => ['true_20112347663@c.us_3EB0ABC_201999999999@c.us'],
        ];
    }

    public function test_ack_for_unknown_conversation_is_ignored_safely(): void
    {
        $this->postEnvelope([
            'id' => 'evt_'.Str::random(20),
            'timestamp' => time() * 1000,
            'event' => 'message.ack',
            'session' => 'default',
            'payload' => [
                'id' => 'true_999@c.us_UNKNOWN',
                'from' => '999@c.us',
                'fromMe' => true,
                'ack' => 3,
                'ackName' => 'READ',
            ],
        ])->assertStatus(202);

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_failed_processing_releases_the_dedupe_reservation(): void
    {
        $this->gateway->throwOnResolve = new RuntimeException('provider unavailable');

        $envelope = $this->messageEnvelope('message', '214457011683409@lid', 'RETRY1');

        $this->postEnvelope($envelope)->assertStatus(500);
        $this->assertSame(0, WhatsAppConversation::count());

        $this->gateway->throwOnResolve = null;

        $this->postEnvelope($envelope)->assertStatus(202);
        $this->assertSame(1, WhatsAppConversation::count());
        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_malformed_message_payload_is_accepted_without_storing_anything(): void
    {
        $this->postEnvelope([
            'id' => 'evt_'.Str::random(20),
            'timestamp' => time() * 1000,
            'event' => 'message',
            'session' => 'default',
            'payload' => ['fromMe' => false, 'body' => 'no identifiers'],
        ])->assertStatus(202);

        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
    }

    private function postEnvelope(array $envelope)
    {
        $body = json_encode($envelope);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $body, self::SECRET),
            'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (time() * 1000),
            'HTTP_X_WEBHOOK_REQUEST_ID' => 'req_'.Str::random(8),
        ], $body);
    }

    private function messageEnvelope(string $event, string $chatId, string $token, array $overrides = []): array
    {
        $fromMe = (bool) ($overrides['fromMe'] ?? false);

        $payload = array_merge([
            'id' => ($fromMe ? 'true_' : 'false_')."{$chatId}_{$token}",
            'from' => $chatId,
            'fromMe' => false,
            'source' => 'app',
            'body' => 'Hello',
            'hasMedia' => false,
            'timestamp' => time(),
        ], $overrides);

        return [
            'id' => 'evt_'.Str::random(20),
            'timestamp' => time() * 1000,
            'event' => $event,
            'session' => 'default',
            'payload' => $payload,
        ];
    }
}
