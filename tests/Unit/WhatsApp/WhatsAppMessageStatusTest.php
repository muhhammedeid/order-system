<?php

namespace Tests\Unit\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use PHPUnit\Framework\TestCase;

class WhatsAppMessageStatusTest extends TestCase
{
    public function test_known_ack_names_map_to_application_statuses(): void
    {
        $this->assertSame(WhatsAppMessageStatus::Pending, WhatsAppMessageStatus::fromAckName('PENDING'));
        $this->assertSame(WhatsAppMessageStatus::Sent, WhatsAppMessageStatus::fromAckName('SERVER'));
        $this->assertSame(WhatsAppMessageStatus::Delivered, WhatsAppMessageStatus::fromAckName('DEVICE'));
        $this->assertSame(WhatsAppMessageStatus::Read, WhatsAppMessageStatus::fromAckName('READ'));
        $this->assertSame(WhatsAppMessageStatus::Failed, WhatsAppMessageStatus::fromAckName('ERROR'));
    }

    public function test_unknown_or_missing_ack_names_have_no_mapping(): void
    {
        $this->assertNull(WhatsAppMessageStatus::fromAckName('SOMETHING_NEW'));
        $this->assertNull(WhatsAppMessageStatus::fromAckName(''));
        $this->assertNull(WhatsAppMessageStatus::fromAckName(null));
    }

    public function test_statuses_only_move_forward(): void
    {
        $this->assertTrue(WhatsAppMessageStatus::Sent->canBeAppliedTo(null));
        $this->assertTrue(WhatsAppMessageStatus::Sent->canBeAppliedTo(WhatsAppMessageStatus::Pending));
        $this->assertTrue(WhatsAppMessageStatus::Delivered->canBeAppliedTo(WhatsAppMessageStatus::Sent));
        $this->assertTrue(WhatsAppMessageStatus::Read->canBeAppliedTo(WhatsAppMessageStatus::Delivered));

        $this->assertFalse(WhatsAppMessageStatus::Pending->canBeAppliedTo(WhatsAppMessageStatus::Sent));
        $this->assertFalse(WhatsAppMessageStatus::Sent->canBeAppliedTo(WhatsAppMessageStatus::Delivered));
        $this->assertFalse(WhatsAppMessageStatus::Read->canBeAppliedTo(WhatsAppMessageStatus::Read));
    }

    public function test_failed_is_terminal_and_only_applies_before_delivery(): void
    {
        $this->assertTrue(WhatsAppMessageStatus::Failed->canBeAppliedTo(WhatsAppMessageStatus::Pending));
        $this->assertTrue(WhatsAppMessageStatus::Failed->canBeAppliedTo(WhatsAppMessageStatus::Sent));
        $this->assertFalse(WhatsAppMessageStatus::Failed->canBeAppliedTo(WhatsAppMessageStatus::Delivered));
        $this->assertFalse(WhatsAppMessageStatus::Failed->canBeAppliedTo(WhatsAppMessageStatus::Read));
        $this->assertFalse(WhatsAppMessageStatus::Sent->canBeAppliedTo(WhatsAppMessageStatus::Failed));
        $this->assertFalse(WhatsAppMessageStatus::Delivered->canBeAppliedTo(WhatsAppMessageStatus::Failed));
    }
}
