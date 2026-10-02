<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Customer;
use App\Support\WhatsApp\Inbox\CustomerMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxCustomerMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_exactly_one_customer_by_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '0112347663']);

        $matched = CustomerMatcher::linkableCustomer('20112347663');

        $this->assertNotNull($matched);
        $this->assertTrue($matched->is($customer));
    }

    public function test_it_matches_the_whatsapp_field(): void
    {
        $customer = Customer::factory()->create(['phone' => '01000000000', 'whatsapp' => '+20112347663']);

        $matched = CustomerMatcher::linkableCustomer('20112347663');

        $this->assertNotNull($matched);
        $this->assertTrue($matched->is($customer));
    }

    public function test_ambiguous_matches_stay_unlinked(): void
    {
        Customer::factory()->create(['phone' => '0112347663']);
        Customer::factory()->create(['whatsapp' => '20112347663']);

        $this->assertNull(CustomerMatcher::linkableCustomer('20112347663'));
    }

    public function test_unknown_numbers_stay_unlinked(): void
    {
        Customer::factory()->create(['phone' => '01000000000']);

        $this->assertNull(CustomerMatcher::linkableCustomer('20112347663'));
    }

    public function test_missing_phone_stays_unlinked(): void
    {
        $this->assertNull(CustomerMatcher::linkableCustomer(null));
        $this->assertNull(CustomerMatcher::linkableCustomer(''));
    }
}
