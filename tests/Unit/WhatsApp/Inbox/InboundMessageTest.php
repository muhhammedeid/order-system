<?php

namespace Tests\Unit\WhatsApp\Inbox;

use App\Enums\WhatsAppMessageType;
use App\Support\WhatsApp\Inbox\InboundMessage;
use PHPUnit\Framework\TestCase;

class InboundMessageTest extends TestCase
{
    public function test_it_parses_a_text_inbound_message(): void
    {
        $message = InboundMessage::fromPayload([
            'id' => 'false_214457011683409@lid_2AFF8BF6FC9E803B6057',
            'from' => '214457011683409@lid',
            'fromMe' => false,
            'source' => 'app',
            'body' => 'مرحبا',
            'hasMedia' => false,
            'timestamp' => 1710481111.853,
        ]);

        $this->assertNotNull($message);
        $this->assertFalse($message->fromMe);
        $this->assertSame('214457011683409@lid', $message->chatId);
        $this->assertSame('2AFF8BF6FC9E803B6057', $message->providerMessageId);
        $this->assertSame(WhatsAppMessageType::Text, $message->type);
        $this->assertSame('مرحبا', $message->body);
        $this->assertSame(1710481111, $message->timestamp);
    }

    public function test_it_classifies_media_location_contact_and_unsupported(): void
    {
        $media = InboundMessage::fromPayload([
            'id' => 'false_111@c.us_A',
            'fromMe' => false,
            'hasMedia' => true,
            'media' => ['mimetype' => 'image/jpeg'],
            'body' => 'caption',
        ]);
        $this->assertSame(WhatsAppMessageType::Image, $media->type);

        $video = InboundMessage::fromPayload([
            'id' => 'false_111@c.us_B',
            'fromMe' => false,
            'hasMedia' => true,
            'media' => ['mimetype' => 'video/mp4'],
        ]);
        $this->assertSame(WhatsAppMessageType::Video, $video->type);

        $document = InboundMessage::fromPayload([
            'id' => 'false_111@c.us_C',
            'fromMe' => false,
            'hasMedia' => true,
            'media' => ['mimetype' => 'application/pdf'],
        ]);
        $this->assertSame(WhatsAppMessageType::Document, $document->type);

        $location = InboundMessage::fromPayload([
            'id' => 'false_111@c.us_D',
            'fromMe' => false,
            'location' => ['latitude' => 1, 'longitude' => 2],
        ]);
        $this->assertSame(WhatsAppMessageType::Location, $location->type);

        $contact = InboundMessage::fromPayload([
            'id' => 'false_111@c.us_E',
            'fromMe' => false,
            'vCards' => ['BEGIN:VCARD'],
        ]);
        $this->assertSame(WhatsAppMessageType::Contact, $contact->type);

        $unsupported = InboundMessage::fromPayload([
            'id' => 'false_111@c.us_F',
            'fromMe' => false,
        ]);
        $this->assertSame(WhatsAppMessageType::Unsupported, $unsupported->type);
    }

    public function test_it_uses_the_recipient_as_the_chat_for_own_messages(): void
    {
        $message = InboundMessage::fromPayload([
            'id' => 'true_20112347663@c.us_ABC',
            'fromMe' => true,
            'to' => '20112347663@c.us',
            'source' => 'app',
            'body' => 'Hi',
        ]);

        $this->assertTrue($message->fromMe);
        $this->assertSame('20112347663@c.us', $message->chatId);
    }

    public function test_it_rejects_non_one_to_one_chats(): void
    {
        foreach (['123@g.us', 'status@broadcast', '123@broadcast', '123@newsletter'] as $chatId) {
            $message = InboundMessage::fromPayload([
                'id' => "false_{$chatId}_A",
                'fromMe' => false,
                'body' => 'x',
            ]);

            $this->assertFalse($message->isOneToOne(), $chatId);
        }
    }

    public function test_it_returns_null_without_a_chat_identifier(): void
    {
        $this->assertNull(InboundMessage::fromPayload(['fromMe' => false, 'body' => 'x']));
    }
}
