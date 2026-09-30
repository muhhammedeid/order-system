<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\WhatsApp\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_egyptian_forms_reuse_customer_without_changing_external_code_or_contact(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '01001234567', 'whatsapp' => '01112345678', 'customer_code' => 'ACC-001',
        ]);
        foreach (['+20 100 123 4567', '00201001234567', '(010) 012-34567', '٠١٠٠١٢٣٤٥٦٧'] as $phone) {
            $matched = Customer::matchOrCreate([
                'name' => 'Store', 'phone' => $phone, 'whatsapp' => '01299999999', 'customer_code' => 'ATTACK',
            ]);
            $this->assertTrue($matched->is($customer));
            $this->assertSame('ACC-001', $matched->customer_code);
            $this->assertSame('01112345678', $matched->whatsapp);
        }
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_phone_locks', 1);
    }

    public function test_legacy_values_and_accounting_codes_are_not_rewritten(): void
    {
        $this->assertSame('0112347663', PhoneNumber::normalize('0112347663'));
        $this->assertSame('201001234567', PhoneNumber::normalize('01001234567'));
        $this->assertTrue(PhoneNumber::isSyntacticallyUsable('٠١٠٠١٢٣٤٥٦٧'));
        $this->assertContains('201001234567', PhoneNumber::candidates('٠١٠٠١٢٣٤٥٦٧'));
        $this->assertNull(PhoneNumber::normalize(' '));
        $customer = Customer::factory()->create(['phone' => '+20 100 123 4567']);
        $this->assertSame('+20 100 123 4567', $customer->phone);
        $this->assertSame('201001234567', $customer->phone_normalized);
    }
}
