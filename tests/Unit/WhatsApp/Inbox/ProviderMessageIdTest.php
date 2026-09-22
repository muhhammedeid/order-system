<?php

namespace Tests\Unit\WhatsApp\Inbox;

use App\Support\WhatsApp\Inbox\ProviderMessageId;
use PHPUnit\Framework\TestCase;

class ProviderMessageIdTest extends TestCase
{
    public function test_it_parses_event_ids_into_direction_chat_and_message(): void
    {
        $parsed = ProviderMessageId::parse('false_214457011683409@lid_2AFF8BF6FC9E803B6057');

        $this->assertSame(false, $parsed['fromMe']);
        $this->assertSame('214457011683409@lid', $parsed['chatId']);
        $this->assertSame('2AFF8BF6FC9E803B6057', $parsed['messageId']);

        $parsed = ProviderMessageId::parse('true_20112347663@c.us_3EB0E36183BA2C52D90B1C');

        $this->assertSame(true, $parsed['fromMe']);
        $this->assertSame('20112347663@c.us', $parsed['chatId']);
        $this->assertSame('3EB0E36183BA2C52D90B1C', $parsed['messageId']);
    }

    public function test_it_rejects_ids_without_chat_context(): void
    {
        $this->assertNull(ProviderMessageId::parse('3EB0E36183BA2C52D90B1C'));
        $this->assertNull(ProviderMessageId::parse('unknown_chat_id'));
        $this->assertNull(ProviderMessageId::parse(null));
        $this->assertNull(ProviderMessageId::parse(''));
    }

    public function test_normalize_reduces_event_ids_and_passes_send_response_ids(): void
    {
        $this->assertSame('ABC', ProviderMessageId::normalize('false_111@c.us_ABC'));
        $this->assertSame('ABC', ProviderMessageId::normalize('true_111@lid_ABC'));
        $this->assertSame('3EB0ABC', ProviderMessageId::normalize('3EB0ABC'));
        $this->assertNull(ProviderMessageId::normalize(null));
    }
}
