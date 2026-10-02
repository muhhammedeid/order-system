<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderTransitionException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function submitOrder(int $quantity = 3): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create([
                'price_visibility' => 'public',
                'price' => 450,
                'size_enabled' => true,
            ]))
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => $quantity]);
        $this->post('/checkout', ['name' => 'Test Store', 'phone' => '01001234567']);

        return Order::query()->firstOrFail();
    }

    public function test_new_orders_store_product_variant_id_on_items(): void
    {
        $order = $this->submitOrder();

        $item = $order->items->first();

        $this->assertNotNull($item->product_variant_id);
        $this->assertNotNull($item->productVariant);
        $this->assertSame(0, $item->delivered_quantity);
        $this->assertSame($item->quantity, $item->remaining_quantity);
    }

    public function test_confirmation_sets_status_without_touching_stock(): void
    {
        $order = $this->submitOrder();

        $variant = $order->items->first()->productVariant;

        $order->confirm();

        $this->assertSame(OrderStatus::Confirmed->value, $order->refresh()->status->value);
        $this->assertSame(10, $variant->refresh()->available_quantity);

        try {
            $order->confirm();
            $this->fail('Expected OrderTransitionException');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(10, $variant->refresh()->available_quantity);
    }

    public function test_confirmation_is_not_blocked_by_zero_available_quantity(): void
    {
        $order = $this->submitOrder();

        $variant = $order->items->first()->productVariant;
        $variant->update(['available_quantity' => 0]);

        $order->confirm();

        $this->assertSame(OrderStatus::Confirmed->value, $order->refresh()->status->value);
        $this->assertSame(0, $variant->refresh()->available_quantity);
    }

    public function test_confirmation_does_not_require_a_variant_reference(): void
    {
        $order = $this->submitOrder();

        $order->items()->update(['product_variant_id' => null]);

        $order->confirm();

        $this->assertSame(OrderStatus::Confirmed->value, $order->refresh()->status->value);
        $this->assertNull($order->items->first()->refresh()->product_variant_id);
    }

    public function test_new_order_cancel_is_terminal_without_stock_effect(): void
    {
        $order = $this->submitOrder();
        $variant = $order->items->first()->productVariant;

        $order->cancel();

        $this->assertSame(OrderStatus::Cancelled->value, $order->refresh()->status->value);
        $this->assertSame(10, $variant->refresh()->available_quantity);

        try {
            $order->cancel();
            $this->fail('Expected second cancellation to be rejected');
        } catch (OrderTransitionException) {
        }

        try {
            $order->confirm();
            $this->fail('Expected cancelled orders to be terminal');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(OrderStatus::Cancelled->value, $order->refresh()->status->value);
    }

    public function test_confirmed_order_cannot_be_cancelled(): void
    {
        $order = $this->submitOrder();
        $order->confirm();

        try {
            $order->cancel();
            $this->fail('Expected confirmed orders to be non-cancellable');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(OrderStatus::Confirmed->value, $order->refresh()->status->value);
    }

    public function test_variant_referenced_by_new_order_cannot_be_deleted(): void
    {
        $order = $this->submitOrder();
        $variant = $order->items->first()->productVariant;

        try {
            $variant->delete();
            $this->fail('Expected deletion to be rejected for a new order');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('مرتبط بطلب نشط', $exception->getMessage());
        }

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_product_referenced_by_new_order_variants_cannot_be_deleted(): void
    {
        $order = $this->submitOrder();
        $product = $order->items->first()->product;

        try {
            $product->delete();
            $this->fail('Expected deletion to be blocked for a new order');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('مرتبطة بطلبات نشطة', $exception->getMessage());
        }

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_variant_referenced_by_confirmed_order_cannot_be_deleted(): void
    {
        $order = $this->submitOrder();
        $order->confirm();

        $variant = $order->items->first()->productVariant;

        try {
            $variant->delete();
            $this->fail('Expected deletion to be rejected');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('مرتبط بطلب نشط', $exception->getMessage());
        }

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_product_with_confirmed_order_variants_cannot_be_deleted(): void
    {
        $order = $this->submitOrder();
        $order->confirm();

        $product = $order->items->first()->product;

        try {
            $product->delete();
            $this->fail('Expected deletion to be blocked');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('مرتبطة بطلبات نشطة', $exception->getMessage());
        }

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_partially_delivered_order_still_protects_its_variant(): void
    {
        $order = $this->submitOrder();
        $order->confirm();

        $variant = $order->items->first()->productVariant;

        DB::table('order_items')->where('order_id', $order->id)->update(['delivered_quantity' => 1]);
        DB::table('orders')->where('id', $order->id)->update(['status' => OrderStatus::PartiallyDelivered->value]);

        try {
            $variant->delete();
            $this->fail('Expected deletion to be rejected for a partially delivered order');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('مرتبط بطلب نشط', $exception->getMessage());
        }

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_terminal_orders_rely_on_snapshots_for_deleted_variants(): void
    {
        $order = $this->submitOrder();
        $order->cancel();

        $variant = $order->items->first()->productVariant;
        $item = $order->items->first();

        $variant->delete();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
        $this->assertNull($item->refresh()->product_variant_id);
        $this->assertNotNull($item->product_code);
        $this->assertNotNull($item->product_name);
    }

    public function test_direct_mass_assignment_of_status_is_ignored(): void
    {
        $order = $this->submitOrder();

        $order->update(['status' => OrderStatus::Delivered->value, 'admin_notes' => 'note kept']);

        $this->assertSame(OrderStatus::New->value, $order->refresh()->status->value);
        $this->assertSame('note kept', $order->admin_notes);
    }

    public function test_admin_notes_persist_and_customer_data_is_immutable(): void
    {
        $order = $this->submitOrder();
        $originalCustomer = $order->customer->id;

        $order->update(['admin_notes' => 'Contact customer about delivery']);

        $this->assertSame('Contact customer about delivery', $order->refresh()->admin_notes);
        $this->assertSame($originalCustomer, $order->refresh()->customer_id);
        $this->assertNull($order->customer_notes);
    }

    public function test_admin_order_pages_render(): void
    {
        $admin = User::factory()->create();
        $order = $this->submitOrder();

        $this->actingAs($admin)->get('/admin/order-management')->assertStatus(200);
        $this->actingAs($admin)->get("/admin/order-management/{$order->getKey()}")->assertStatus(200);
        $this->actingAs($admin)->get("/admin/order-management/{$order->getKey()}/edit")->assertStatus(200);
        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200);
    }

    public function test_request_price_items_show_no_price_in_admin_view(): void
    {
        $secretVariant = ProductVariant::factory()
            ->for(Product::factory()->requestPrice()->create(['name' => 'Secret Shoe', 'price' => 321.55, 'size_enabled' => true]))
            ->create(['available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $secretVariant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $order = Order::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create())->get("/admin/order-management/{$order->getKey()}");

        $response->assertSee('السعر عند الطلب');
        $this->assertStringNotContainsString('321.55', $response->getContent());
    }
}
