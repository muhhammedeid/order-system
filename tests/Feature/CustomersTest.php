<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_persists_with_all_fields(): void
    {
        $customer = Customer::query()->create([
            'customer_code' => 'C100',
            'name' => 'Mohamed Store',
            'company_name' => 'Mohamed Trading',
            'phone' => '01001234567',
            'whatsapp' => '01001234567',
            'governorate' => 'Cairo',
            'city' => 'Nasr City',
            'address' => '12 Main St',
            'notes' => 'Wholesale client',
        ]);

        $customer->refresh();

        $this->assertSame('C100', $customer->customer_code);
        $this->assertSame('Mohamed Store', $customer->name);
        $this->assertSame('Mohamed Trading', $customer->company_name);
        $this->assertSame('01001234567', $customer->phone);
        $this->assertSame('Cairo', $customer->governorate);
        $this->assertSame('Nasr City', $customer->city);
        $this->assertSame('Wholesale client', $customer->notes);
    }

    public function test_customer_code_must_be_unique_when_set(): void
    {
        Customer::factory()->create(['customer_code' => 'C100']);

        $this->expectException(QueryException::class);

        Customer::factory()->create(['customer_code' => 'C100']);
    }

    public function test_multiple_customers_may_have_null_code(): void
    {
        Customer::factory()->count(3)->withoutCode()->create();

        $this->assertDatabaseCount('customers', 3);
        $this->assertSame(3, Customer::query()->whereNull('customer_code')->count());
    }

    public function test_customer_code_is_trimmed_and_empty_becomes_null(): void
    {
        $customer = Customer::factory()->create(['customer_code' => '  C-9  ']);

        $this->assertSame('C-9', $customer->customer_code);

        $customer->update(['customer_code' => '   ']);
        $customer->refresh();

        $this->assertNull($customer->customer_code);
    }

    public function test_name_and_phone_are_required_and_trimmed(): void
    {
        $data = Customer::validate([
            'name' => '  Trimmed Store  ',
            'phone' => ' 01001234567 ',
        ]);

        $this->assertSame('Trimmed Store', $data['name']);
        $this->assertSame('01001234567', $data['phone']);

        $this->expectException(ValidationException::class);

        Customer::validate(['name' => '   ', 'phone' => '01001234567']);
    }

    public function test_search_by_name(): void
    {
        Customer::factory()->create(['name' => 'Zahra Store', 'phone' => '01001111111']);
        Customer::factory()->create(['name' => 'Alpha Store', 'phone' => '01002222222']);

        Livewire::test(ListCustomers::class)
            ->searchTable('Zahra')
            ->assertSee('Zahra Store')
            ->assertDontSee('Alpha Store');
    }

    public function test_search_by_phone(): void
    {
        Customer::factory()->create(['name' => 'Store One', 'phone' => '01001111111']);
        Customer::factory()->create(['name' => 'Store Two', 'phone' => '01002222222']);

        Livewire::test(ListCustomers::class)
            ->searchTable('01002222222')
            ->assertSee('Store Two')
            ->assertDontSee('Store One');
    }

    public function test_search_by_customer_code(): void
    {
        Customer::factory()->create(['name' => 'Store One', 'customer_code' => 'C100']);
        Customer::factory()->create(['name' => 'Store Two', 'customer_code' => 'C200']);

        Livewire::test(ListCustomers::class)
            ->searchTable('C200')
            ->assertSee('Store Two')
            ->assertDontSee('Store One');
    }

    public function test_customer_can_be_deleted(): void
    {
        $customer = Customer::factory()->create();

        $customer->delete();

        $this->assertDatabaseMissing('customers', ['id' => $customer->getKey()]);
    }
}
