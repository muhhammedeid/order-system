<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_document_is_arabic_first_and_rtl(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_storefront_renders_open_graph_tags_for_whatsapp_sharing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:site_name"', false)
            ->assertSee('MAI SHOES', false);
    }

    public function test_product_page_renders_open_graph_image_and_meta(): void
    {
        $product = Product::factory()->create(['name' => 'OG Shoe', 'price_visibility' => 'public', 'price' => 100]);
        $product->images()->create(['image_path' => 'products/images/og.png', 'sort_order' => 1]);

        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee('products/images/og.png', false)
            ->assertSee('og:type', false);
    }

    public function test_request_price_product_never_leaks_price_into_the_document(): void
    {
        $product = Product::factory()->requestPrice()->create(['price' => 4321.98]);

        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertDontSee('4321.98', false);
    }

    public function test_storefront_pages_render_without_server_errors(): void
    {
        $category = Category::factory()->create(['name' => 'Running']);
        $product = Product::factory()->for($category)->create(['price_visibility' => 'public', 'price' => 250, 'size_enabled' => true]);
        $variant = ProductVariant::factory()->for($product)->create(['available_quantity' => 4]);

        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-2026-00001',
            'customer_id' => $customer->getKey(),
            'status' => 'new',
            'total_quantity' => 1,
        ]);

        $this->get('/')->assertOk();
        $this->get('/catalog')->assertOk();
        $this->get("/catalog?category={$category->getKey()}")->assertOk();
        $this->get('/catalog?search=Running')->assertOk();
        $this->get("/product/{$product->slug}")->assertOk();
        $this->get('/cart')->assertOk();

        $this->withSession(['cart' => [
            ['variant_id' => $variant->getKey(), 'quantity' => 2],
        ]])->get('/cart')->assertOk();

        $this->withSession(['cart' => [
            ['variant_id' => $variant->getKey(), 'quantity' => 2],
        ]])->get('/checkout')->assertOk();

        $this->get("/order/success/{$order->order_number}")->assertOk();
    }
}
