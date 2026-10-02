<?php

namespace Tests\Unit\WhatsApp;

use App\Support\WhatsApp\Inbox\MarketingOptOut;
use PHPUnit\Framework\TestCase;

class MarketingOptOutTest extends TestCase
{
    public function test_approved_keywords_match_as_whole_messages(): void
    {
        foreach ([
            'stop',
            'unsubscribe',
            'ايقاف الاشتراك',
            'الغاء الاشتراك',
            'لا اريد رسائل',
            'لا اريد عروض',
        ] as $keyword) {
            $this->assertTrue(MarketingOptOut::matches($keyword), $keyword);
        }
    }

    public function test_arabic_spelling_variants_are_normalized(): void
    {
        $this->assertTrue(MarketingOptOut::matches('إيقاف الاشتراك'));
        $this->assertTrue(MarketingOptOut::matches('إلغاء الاشتراك'));
        $this->assertTrue(MarketingOptOut::matches('لا أريد رسائل'));
        $this->assertTrue(MarketingOptOut::matches('لا أريد عروض'));
        $this->assertTrue(MarketingOptOut::matches('إيــقاف الاشتراك'));
        $this->assertTrue(MarketingOptOut::matches('إيقَاف الاشتراك'));
        $this->assertTrue(MarketingOptOut::matches('  إيقاف   الاشتراك  '));
        $this->assertTrue(MarketingOptOut::matches('إيقاف الاشتراك.'));
        $this->assertTrue(MarketingOptOut::matches('«إلغاء الاشتراك»'));
    }

    public function test_invisible_format_characters_and_unicode_spaces_are_ignored(): void
    {
        $this->assertTrue(MarketingOptOut::matches("إيقاف\u{200D} الاشتراك"));
        $this->assertTrue(MarketingOptOut::matches("إيقاف\u{200F} الاشتراك"));
        $this->assertTrue(MarketingOptOut::matches("إيقاف\u{200B}الاشتراك"));
        $this->assertTrue(MarketingOptOut::matches("إيقاف\u{00A0}الاشتراك"));
        $this->assertTrue(MarketingOptOut::matches("لا\u{00A0}أريد\u{00A0}رسائل"));
        $this->assertTrue(MarketingOptOut::matches("\u{FEFF}stop\u{FEFF}"));
    }

    public function test_english_case_whitespace_and_punctuation_are_normalized(): void
    {
        $this->assertTrue(MarketingOptOut::matches('STOP'));
        $this->assertTrue(MarketingOptOut::matches('  Unsubscribe  '));
        $this->assertTrue(MarketingOptOut::matches('stop!'));
        $this->assertTrue(MarketingOptOut::matches('🛑 stop 🛑'));
    }

    public function test_bare_ambiguous_words_are_not_opt_outs(): void
    {
        $this->assertFalse(MarketingOptOut::matches('إلغاء'));
        $this->assertFalse(MarketingOptOut::matches('إيقاف'));
        $this->assertFalse(MarketingOptOut::matches('الغاء'));
        $this->assertFalse(MarketingOptOut::matches('ايقاف'));
    }

    public function test_order_language_is_never_an_opt_out(): void
    {
        $this->assertFalse(MarketingOptOut::matches('إلغاء الطلب'));
        $this->assertFalse(MarketingOptOut::matches('أريد إلغاء الطلب'));
        $this->assertFalse(MarketingOptOut::matches('stop the order'));
        $this->assertFalse(MarketingOptOut::matches('لا اريد رسائل من فضلك'));
        $this->assertFalse(MarketingOptOut::matches('من فضلك إيقاف الاشتراك'));
    }

    public function test_null_and_blank_bodies_never_match(): void
    {
        $this->assertFalse(MarketingOptOut::matches(null));
        $this->assertFalse(MarketingOptOut::matches(''));
        $this->assertFalse(MarketingOptOut::matches('   '));
    }
}
