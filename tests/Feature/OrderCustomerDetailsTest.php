<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderCustomerDetailsTest extends TestCase
{
    use RefreshDatabase;

    public static function orderPages(): array
    {
        return [
            'view' => ['order-management', '', false],
            'review' => ['order-management', '/confirm', false],
            'delivered history' => ['orders', '', true],
        ];
    }

    #[DataProvider('orderPages')]
    public function test_initial_order_page_displays_complete_customer_contact_details(string $resource, string $suffix, bool $delivered): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Order contact fixture',
            'phone' => '01009998877',
            'whatsapp' => '01109998877',
            'company_name' => 'Order company fixture',
            'governorate' => 'Cairo contact fixture',
            'city' => 'Order city fixture',
            'address' => 'Order address fixture',
        ]);
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'customer_id' => $customer->getKey(),
            'total_quantity' => 0,
        ]);
        if ($delivered) {
            $order->forceFill(['status' => OrderStatus::Delivered])->save();
        }

        $response = $this->actingAs(User::factory()->create())
            ->get('/admin/'.$resource.'/'.$order->getKey().$suffix)
            ->assertOk();

        foreach (['name', 'phone', 'whatsapp', 'company_name', 'governorate', 'city', 'address'] as $field) {
            $response->assertSee($customer->getAttribute($field));
        }
    }
}
