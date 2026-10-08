<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_creates_customer_when_phone_is_new(): void
    {
        $customer = Customer::matchOrCreate([
            'name' => 'New Store',
            'phone' => '01001234567',
            'company_name' => 'Trading Co',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Store',
            'phone' => '01001234567',
        ]);

        $this->assertNull($customer->password ?? null);
        $this->assertDatabaseMissing('customers', ['password' => null]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_customer_is_matched_by_exact_phone(): void
    {
        $existing = Customer::factory()->create(['phone' => '01001234567', 'name' => 'Old Name', 'city' => 'Saved City']);

        $customer = Customer::matchOrCreate([
            'name' => 'Refreshed Name',
            'phone' => '01001234567',
            'city' => 'Nasr City',
        ]);

        $this->assertTrue($customer->is($existing));
        $this->assertSame('Old Name', $customer->name);
        $this->assertSame('Saved City', $customer->city);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_duplicate_phones_match_the_earliest_customer(): void
    {
        $first = Customer::factory()->create(['phone' => '01001234567', 'name' => 'First']);
        Customer::factory()->create(['phone' => '01001234567', 'name' => 'Second']);

        $matched = Customer::matchOrCreate([
            'name' => 'Matched',
            'phone' => '01001234567',
        ]);

        $this->assertTrue($matched->is($first));
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_matching_requires_name_and_phone(): void
    {
        $this->expectException(ValidationException::class);

        Customer::matchOrCreate(['name' => '', 'phone' => '']);
    }

    public function test_no_login_account_is_created_for_customers(): void
    {
        $customer = Customer::matchOrCreate([
            'name' => 'Guest Store',
            'phone' => '01001234567',
        ]);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertDatabaseMissing('users', ['email' => '01001234567']);
        $this->assertDatabaseCount('users', 0);
    }
}
