<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\WahaSentMessage;
use PHPUnit\Framework\TestCase;

class SentMessageTest extends TestCase
{
    public function test_it_reads_the_noweb_key_id_and_message_timestamp(): void
    {
        $message = WahaSentMessage::fromArray([
            'key' => ['remoteJid' => '111@s.whatsapp.net', 'fromMe' => true, 'id' => '3EB03EECBDF3D92DA9EBB4'],
            'message' => ['extendedTextMessage' => ['text' => 'x']],
            'messageTimestamp' => '1790068606',
            'status' => 'PENDING',
        ]);

        $this->assertSame('3EB03EECBDF3D92DA9EBB4', $message->providerId);
        $this->assertSame(1790068606, $message->timestamp);
    }

    public function test_it_keeps_the_documented_id_and_timestamp_shape(): void
    {
        $message = WahaSentMessage::fromArray([
            'id' => 'true_111@c.us_ABC',
            'timestamp' => 1700000000,
        ]);

        $this->assertSame('ABC', $message->providerId);
        $this->assertSame(1700000000, $message->timestamp);
    }

    public function test_it_tolerates_unknown_shapes(): void
    {
        $message = WahaSentMessage::fromArray([]);

        $this->assertSame('', $message->providerId);
        $this->assertNull($message->timestamp);
    }
}
