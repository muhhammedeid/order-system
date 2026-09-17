<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransitionException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function submitOrder(): Order
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['price_visibility' => 'public', 'price' => 450]))
            ->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);
        $this->post('/checkout', ['name' => 'Test Store', 'phone' => '01001234567']);

        return Order::query()->firstOrFail();
    }

    public function test_new_orders_store_product_variant_id_on_items(): void
    {
        $order = $this->submitOrder();

        $item = $order->items->first();

        $this->assertNotNull($item->product_variant_id);
        $this->assertNotNull($item->productVariant);
    }

    public function test_confirmation_decrements_using_variant_reference_exactly_once(): void
    {
        $order = $this->submitOrder();

        $item = $order->items->first();
        $variant = $item->productVariant;
        $ordered = $item->quantity;
        $before = $variant->available_quantity;

        $order->confirm();

        $this->assertSame($before - $ordered, $variant->refresh()->available_quantity);
        $this->assertSame('confirmed', $order->refresh()->status->value);

        // re-confirming must be rejected and must not decrement again
        try {
            $order->confirm();
            $this->fail('Expected OrderTransitionException');
        } catch (OrderTransitionException) {
        }

        $this->assertSame($before - $ordered, $variant->refresh()->available_quantity);
    }

    public function test_confirmation_uses_variant_reference_not_snapshot_text(): void
    {
        $order = $this->submitOrder();

        $item = $order->items->first();
        $variant = $item->productVariant;

        // mutate snapshot text so product_id+color+size text matching would fail
        $item->update(['color' => 'CHANGED-SNAPSHOT', 'size' => '99']);

        $order->confirm();

        $this->assertSame(10 - $item->quantity, $variant->refresh()->available_quantity);
    }

    public function test_missing_variant_reference_rejects_confirmation_safely(): void
    {
        $order = $this->submitOrder();

        $order->items()->update(['product_variant_id' => null]);

        try {
            $order->confirm();
            $this->fail('Expected exception');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('غير مرتبط بمقاس محدد', $exception->getMessage());
        }

        $this->assertSame('new', $order->refresh()->status->value);

        $variant = ProductVariant::query()->first();
        $this->assertSame(10, $variant->available_quantity);
    }

    public function test_new_order_with_deleted_variant_cannot_be_confirmed(): void
    {
        $order = $this->submitOrder();

        $order->items->first()->productVariant->delete();

        try {
            $order->confirm();
            $this->fail('Expected exception');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('لا يمكن تأكيد الطلب', $exception->getMessage());
        }

        $this->assertNull($order->items->first()->refresh()->product_variant_id);
        $this->assertSame('new', $order->refresh()->status->value);
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
            $this->assertStringContainsString('مرتبط بطلب مؤكد', $exception->getMessage());
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
            $this->assertStringContainsString('مرتبطة بطلبات مؤكدة', $exception->getMessage());
        }

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_confirmed_cancel_restores_the_same_variant_exactly_once(): void
    {
        $order = $this->submitOrder();
        $order->confirm();

        $variant = $order->items->first()->productVariant;
        $afterConfirm = $variant->refresh()->available_quantity;

        $order->cancel();

        $this->assertSame(10, $variant->refresh()->available_quantity);
        $this->assertSame('cancelled', $order->refresh()->status->value);

        try {
            $order->cancel();
            $this->fail('Expected second cancellation to be rejected');
        } catch (OrderTransitionException) {
        }

        $this->assertSame(10, $variant->refresh()->available_quantity);
        $this->assertSame('cancelled', $order->refresh()->status->value);
    }

    public function test_new_cancel_has_no_quantity_effect(): void
    {
        $order = $this->submitOrder();
        $variant = $order->items->first()->productVariant;

        $order->cancel();

        $this->assertSame(10, $variant->refresh()->available_quantity);
        $this->assertSame('cancelled', $order->refresh()->status->value);
    }

    public function test_confirmed_export_does_not_decrement_again(): void
    {
        $order = $this->submitOrder();
        $order->confirm();

        $variant = $order->items->first()->productVariant;
        $afterConfirm = $variant->refresh()->available_quantity;

        $order->markExported();

        $this->assertSame($afterConfirm, $variant->refresh()->available_quantity);
        $this->assertSame('exported', $order->refresh()->status->value);

        try {
            $order->confirm();
            $this->fail('Expected exception');
        } catch (OrderTransitionException) {
        }

        try {
            $order->cancel();
            $this->fail('Expected exported orders to be terminal');
        } catch (OrderTransitionException) {
        }

        $this->assertSame($afterConfirm, $variant->refresh()->available_quantity);
    }

    public function test_insufficient_availability_at_confirmation_rejects_with_clear_error(): void
    {
        $order = $this->submitOrder();

        $variant = $order->items->first()->productVariant;
        $variant->update(['available_quantity' => 0]);

        try {
            $order->confirm();
            $this->fail('Expected exception');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString($variant->product->name, $exception->getMessage());
            $this->assertStringContainsString('Black', $exception->getMessage());
            $this->assertStringContainsString('41', $exception->getMessage());
        }

        $this->assertSame('new', $order->refresh()->status->value);
        $this->assertSame(0, $variant->refresh()->available_quantity);
    }

    public function test_direct_mass_assignment_of_status_is_ignored(): void
    {
        $order = $this->submitOrder();

        $order->update(['status' => 'exported', 'admin_notes' => 'note kept']);

        $this->assertSame('new', $order->refresh()->status->value);
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
        $order->confirm();

        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200);
        $this->actingAs($admin)->get("/admin/orders/{$order->getKey()}")->assertStatus(200);
        $this->actingAs($admin)->get("/admin/orders/{$order->getKey()}/edit")->assertStatus(200);
    }

    public function test_request_price_items_show_no_price_in_admin_view(): void
    {
        $secretVariant = ProductVariant::factory()
            ->for(Product::factory()->requestPrice()->create(['name' => 'Secret Shoe', 'price' => 321.55]))
            ->create(['available_quantity' => 10]);

        $this->post('/cart/add', ['variant_id' => $secretVariant->id, 'quantity' => 1]);
        $this->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $order = Order::query()->firstOrFail();

        $response = $this->actingAs(User::factory()->create())->get("/admin/orders/{$order->getKey()}");

        $response->assertSee('السعر عند الطلب');
        $this->assertStringNotContainsString('321.55', $response->getContent());
    }
}
