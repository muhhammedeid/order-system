<?php

namespace Tests\Unit\WhatsApp;

use App\Support\WhatsApp\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_syntax_rejects_malformed_input_without_discarding_it(): void
    {
        foreach (['abc201001234567xyz', '20100abc1234567', "20100123\n4567", "20100123\0".'4567', "20100123\u{202E}4567", '20100+1234567', '++201001234567', '12345', '1234567890123456', "20100123\xFF4567"] as $number) {
            $this->assertFalse(PhoneNumber::isSyntacticallyUsable($number));
        }
    }

    public function test_syntax_preserves_supported_local_and_international_formats(): void
    {
        foreach (['01001234567', '+20 100 123 4567', '00201001234567', '٠١٠٠١٢٣٤٥٦٧', '۰۱۰۰۱۲۳۴۵۶۷', '+44 (7700) 900-123', '+٤٤ ٧٧٠٠ ٩٠٠١٢٣', '123456', '123456789012345'] as $number) {
            $this->assertTrue(PhoneNumber::isSyntacticallyUsable($number));
        }
    }

    public function test_it_extracts_a_phone_only_from_regular_c_us_ids(): void
    {
        $this->assertSame('201234567890', PhoneNumber::fromChatId('201234567890@c.us'));
        $this->assertNull(PhoneNumber::fromChatId('214457011683409@lid'));
        $this->assertNull(PhoneNumber::fromChatId('status@broadcast'));
        $this->assertNull(PhoneNumber::fromChatId('123@g.us'));
        $this->assertNull(PhoneNumber::fromChatId('12345@c.us'));
        $this->assertNull(PhoneNumber::fromChatId(null));
    }

    public function test_candidates_include_international_and_national_spellings(): void
    {
        $candidates = PhoneNumber::candidates('20112347663');

        $this->assertContains('20112347663', $candidates);
        $this->assertContains('+20112347663', $candidates);
        $this->assertContains('0112347663', $candidates);
    }

    public function test_candidates_include_the_international_form_for_local_numbers(): void
    {
        $candidates = PhoneNumber::candidates('0112347663');

        $this->assertContains('0112347663', $candidates);
        $this->assertContains('20112347663', $candidates);
        $this->assertContains('112347663', $candidates);
    }

    public function test_candidates_strip_an_international_prefix(): void
    {
        $candidates = PhoneNumber::candidates('0020112347663');

        $this->assertContains('20112347663', $candidates);
    }

    public function test_candidates_ignore_non_numeric_input(): void
    {
        $this->assertSame([], PhoneNumber::candidates('not-a-phone'));
    }
}
