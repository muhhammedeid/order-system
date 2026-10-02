<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Enums\WhatsAppMarketingStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Models\Customer;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Fakes\FakeWhatsAppGateway;
use Tests\TestCase;

class MarketingOptOutIngestionTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        config()->set('whatsapp.webhook.secret', self::SECRET);
        config()->set('whatsapp.webhook.tolerance', 300);

        Cache::flush();

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_each_approved_keyword_unsubscribes_a_linked_customer(): void
    {
        $keywords = ['stop', 'unsubscribe', 'ايقاف الاشتراك', 'الغاء الاشتراك', 'لا اريد رسائل', 'لا اريد عروض'];

        foreach ($keywords as $index => $keyword) {
            [$customer, $chatId] = $this->linkedCustomer($index, 'KEY');

            $this->postEnvelope($this->messageEnvelope('message', $chatId, 'KEY'.$index, ['body' => $keyword]))
                ->assertStatus(202);

            $customer->refresh();

            $this->assertSame(WhatsAppMarketingStatus::Unsubscribed, $customer->whatsapp_marketing_status, $keyword);
            $this->assertNotNull($customer->whatsapp_marketing_opted_out_at, $keyword);
        }
    }

    public function test_arabic_and_english_spelling_variants_are_normalized(): void
    {
        $variants = ['إيقاف الاشتراك', 'إلغاء الاشتراك', 'لا أريد رسائل', 'لا أريد عروض', '  STOP!  ', "إيقاف\u{200F} الاشتراك"];

        foreach ($variants as $index => $variant) {
            [$customer, $chatId] = $this->linkedCustomer($index, 'VAR');

            $this->postEnvelope($this->messageEnvelope('message', $chatId, 'VAR'.$index, ['body' => $variant]))
                ->assertStatus(202);

            $this->assertSame(
                WhatsAppMarketingStatus::Unsubscribed,
                $customer->refresh()->whatsapp_marketing_status,
                $variant,
            );
        }
    }

    public function test_ambiguous_order_messages_never_unsubscribe(): void
    {
        $messages = ['إلغاء الطلب', 'إلغاء', 'إيقاف', 'أريد إلغاء الطلب', 'stop the order'];

        foreach ($messages as $index => $body) {
            [$customer, $chatId] = $this->linkedCustomer($index, 'AMB');

            $this->postEnvelope($this->messageEnvelope('message', $chatId, 'AMB'.$index, ['body' => $body]))
                ->assertStatus(202);

            $customer->refresh();

            $this->assertSame(WhatsAppMarketingStatus::Unknown, $customer->whatsapp_marketing_status, $body);
            $this->assertNull($customer->whatsapp_marketing_opted_out_at, $body);
            $this->assertDatabaseHas('whatsapp_messages', ['body' => $body]);
        }
    }

    public function test_the_opt_out_message_is_still_persisted_and_the_conversation_is_unchanged(): void
    {
        [$customer, $chatId] = $this->linkedCustomer(1, 'KEEP');

        $this->postEnvelope($this->messageEnvelope('message', $chatId, 'KEEP1', ['body' => 'stop']))
            ->assertStatus(202);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertSame($customer->id, $conversation->customer_id);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('stop', $conversation->last_message_preview);

        $message = $conversation->messages()->firstOrFail();

        $this->assertSame(WhatsAppMessageDirection::Inbound, $message->direction);
        $this->assertSame('stop', $message->body);
    }

    public function test_unlinked_conversations_never_mutate_a_customer(): void
    {
        $customer = Customer::factory()->create(['phone' => '0112347663']);
        $this->gateway->resolvedPhone = null;

        $this->postEnvelope($this->messageEnvelope('message', '214457011683409@lid', 'UNLINK1', ['body' => 'stop']))
            ->assertStatus(202);

        $conversation = WhatsAppConversation::query()->firstOrFail();

        $this->assertNull($conversation->customer_id);
        $this->assertSame(1, $conversation->messages()->count());

        $customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Unknown, $customer->whatsapp_marketing_status);
        $this->assertNull($customer->whatsapp_marketing_opted_out_at);
    }

    public function test_phone_sent_outbound_messages_never_trigger_opt_out(): void
    {
        [$customer, $chatId] = $this->linkedCustomer(1, 'PHONE');

        $this->postEnvelope($this->messageEnvelope('message.any', $chatId, 'PHONE1', [
            'fromMe' => true,
            'to' => $chatId,
            'source' => 'app',
            'body' => 'stop',
        ]))->assertStatus(202);

        $customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Unknown, $customer->whatsapp_marketing_status);
        $this->assertNull($customer->whatsapp_marketing_opted_out_at);

        $this->assertSame(
            WhatsAppMessageDirection::Outbound,
            WhatsAppMessage::query()->firstOrFail()->direction,
        );
    }

    public function test_api_sourced_and_from_me_events_never_trigger_opt_out(): void
    {
        [$customer, $chatId] = $this->linkedCustomer(1, 'API');

        $this->postEnvelope($this->messageEnvelope('message.any', $chatId, 'API1', [
            'fromMe' => true,
            'to' => $chatId,
            'source' => 'api',
            'body' => 'stop',
        ]))->assertStatus(202);

        $this->postEnvelope($this->messageEnvelope('message', $chatId, 'API2', [
            'fromMe' => true,
            'to' => $chatId,
            'body' => 'stop',
        ]))->assertStatus(202);

        $customer->refresh();

        $this->assertSame(WhatsAppMarketingStatus::Unknown, $customer->whatsapp_marketing_status);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_already_unsubscribed_customers_keep_their_opt_out_timestamp(): void
    {
        [$customer, $chatId] = $this->linkedCustomer(1, 'AGAIN');

        $customer->update(['whatsapp_marketing_status' => 'unsubscribed']);
        $originalOptOut = $customer->refresh()->whatsapp_marketing_opted_out_at;

        $this->travel(5)->seconds();

        $this->postEnvelope($this->messageEnvelope('message', $chatId, 'AGAIN1', ['body' => 'stop']))
            ->assertStatus(202);

        $customer->refresh();

        $this->assertTrue($customer->whatsapp_marketing_opted_out_at->equalTo($originalOptOut));
        $this->assertSame(1, WhatsAppConversation::query()->firstOrFail()->messages()->count());
    }

    /**
     * @return array{0: Customer, 1: string}
     */
    private function linkedCustomer(int $index, string $token): array
    {
        $localPhone = '0112347'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        $international = '20'.substr($localPhone, 1);
        $chatId = $international.'@c.us';

        $customer = Customer::factory()->create(['phone' => $localPhone]);
        $this->gateway->resolvedPhone = $international;

        return [$customer, $chatId];
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
