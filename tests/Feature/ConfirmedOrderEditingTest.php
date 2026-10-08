<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderManagement\Pages\EditOrder;
use App\Models\OrderItemColorQuantity;
use App\Models\OrderTransitionException;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTestOrders;
use Tests\TestCase;

class ConfirmedOrderEditingTest extends TestCase
{
    use CreatesTestOrders;
    use RefreshDatabase;

    public function test_confirmed_multicolor_quantity_can_increase_and_decrease_from_edit_form(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->createOrderWithCustomer();
        $item = $order->items()->firstOrFail();
        $item->update(['color' => 'Black, White, Red', 'color_count' => 3]);
        $order->recalculateTotalQuantity();
        $order->confirm();
        $colorIds = $item->colorQuantities()->pluck('id')->all();

        foreach ([20, 5] as $requested) {
            Livewire::test(EditOrder::class, ['record' => $order->id])
                ->fillForm(['items' => [[
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'requested_quantity' => $requested,
                ]]])
                ->assertActionExists('saveChanges', fn ($action): bool => $action->getColor() === 'success')
                ->callAction('saveChanges')
                ->assertHasNoFormErrors();

            $this->assertSame($requested * 3, $order->refresh()->total_quantity);
            $this->assertSame(OrderStatus::Confirmed, $order->status);
            $this->assertSame($requested * 3, $item->refresh()->quantity);
            $this->assertSame('Black, White, Red', $item->color);
            $this->assertSame($colorIds, $item->colorQuantities()->pluck('id')->all());
            $this->assertSame([$requested, $requested, $requested], $item->colorQuantities()->pluck('requested_quantity')->all());
            $this->assertSame(0, $item->delivered_quantity);
            $this->assertSame(100, $item->productVariant->available_quantity);
        }
    }

    public function test_admin_can_save_confirmed_items_preserving_status_and_snapshots(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->createOrderWithCustomer();
        $order->confirm();
        $item = $order->items()->firstOrFail();
        $variant = $item->productVariant;
        $snapshot = $item->product_name;
        $variant->product->update(['name' => 'Changed catalog name']);

        $this->get("/admin/order-management/{$order->id}/edit")->assertOk();
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->fillForm(['items' => [[
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'requested_quantity' => 15,
            ]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(15, $order->total_quantity);
        $this->assertSame($snapshot, $item->refresh()->product_name);
        $this->assertSame(0, $item->delivered_quantity);
        $this->assertSame(15, $item->colorQuantities()->sum('requested_quantity'));
        $this->assertSame(15, (int) OrderItemColorQuantity::outstanding()->forProductionOrders()->sum('requested_quantity'));
        $this->assertSame(100, $variant->refresh()->available_quantity);
    }

    public function test_confirmed_items_can_be_added_replaced_and_removed(): void
    {
        $order = $this->createOrderWithCustomer();
        $order->confirm();
        $original = $order->items()->firstOrFail();
        $variant = Product::factory()->requestPrice()->create(['size_enabled' => true])
            ->variants()->create(['color' => 'White', 'size' => '42', 'available_quantity' => 0]);
        $order->updateItems([
            ['id' => $original->id, 'product_variant_id' => $original->product_variant_id, 'quantity' => 10],
            ['product_variant_id' => $variant->id, 'quantity' => 3],
        ]);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(13, $order->total_quantity);

        $order->updateItems([['id' => $original->id, 'product_variant_id' => $variant->id, 'quantity' => 7]]);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(7, $order->total_quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame($variant->product->product_code, $original->refresh()->product_code);
        $this->assertNull($original->unit_price);
        $this->assertSame(7, (int) OrderItemColorQuantity::outstanding()->forProductionOrders()->sum('requested_quantity'));
        $this->assertSame(0, $variant->refresh()->available_quantity);
    }

    public function test_stale_confirmed_form_cannot_edit_a_now_delivered_order(): void
    {
        $order = $this->createOrderWithCustomer();
        $order->confirm();
        $order->refresh();
        $item = $order->items()->firstOrFail();
        $order->fresh()->deliverAllRemaining();

        try {
            $order->updateItems([['id' => $item->id, 'product_variant_id' => $item->product_variant_id, 'quantity' => 20]]);
            $this->fail('Expected delivered order edits to be rejected');
        } catch (OrderTransitionException) {
            $this->assertSame(10, $item->refresh()->quantity);
            $this->assertSame(10, $item->delivered_quantity);
            $this->assertFalse($order->fresh()->isEditable());
        }
    }
}
