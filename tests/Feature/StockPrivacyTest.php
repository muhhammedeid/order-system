<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\VariantSize;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_STOCK = 987654;

    private function sizedProductWithSecretStock(): array
    {
        VariantSize::create(['name' => '41', 'sort_order' => 1, 'active' => true]);

        $product = Product::factory()->create([
            'name' => 'Private Stock Shoe',
            'price_visibility' => 'public',
            'price' => 450,
            'size_enabled' => true,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Black',
            'size' => '41',
            'available_quantity' => self::SECRET_STOCK,
        ]);

        return [$product, $variant];
    }

    public function test_home_and_catalog_pages_never_expose_stock(): void
    {
        $this->sizedProductWithSecretStock();

        foreach (['/', '/catalog'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->missing('products.data.0.available_quantity'));

            $this->assertStringNotContainsString('available_quantity', $response->getContent(), "Leaked key on {$url}");
            $this->assertStringNotContainsString((string) self::SECRET_STOCK, $response->getContent(), "Leaked stock on {$url}");
        }
    }

    public function test_product_page_never_exposes_stock(): void
    {
        [$product] = $this->sizedProductWithSecretStock();

        $response = $this->get("/product/{$product->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('product.size_enabled', true)
            ->missing('variants.0.sizes.0.available_quantity'));

        $this->assertStringNotContainsString('available_quantity', $response->getContent());
        $this->assertStringNotContainsString((string) self::SECRET_STOCK, $response->getContent());
    }

    public function test_cart_and_checkout_pages_never_expose_stock(): void
    {
        [$product, $variant] = $this->sizedProductWithSecretStock();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 2]);

        foreach (['/cart', '/checkout'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->where('items.0.size', '41')
                ->missing('items.0.available_quantity'));

            $this->assertStringNotContainsString('available_quantity', $response->getContent(), "Leaked key on {$url}");
            $this->assertStringNotContainsString((string) self::SECRET_STOCK, $response->getContent(), "Leaked stock on {$url}");
        }
    }

    public function test_quantity_above_stock_is_accepted_without_a_stock_message(): void
    {
        [, $variant] = $this->sizedProductWithSecretStock();

        $response = $this->from('/product/x')
            ->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => self::SECRET_STOCK + 1]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertSame(self::SECRET_STOCK + 1, Cart::count());
        $this->assertSame(self::SECRET_STOCK, $variant->refresh()->available_quantity);
    }

    public function test_checkout_does_not_leak_stock_through_errors(): void
    {
        [$product, $variant] = $this->sizedProductWithSecretStock();

        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1]);

        // Product becomes inactive; the resulting message must not mention stock.
        $product->update(['active' => false]);

        $response = $this->from('/checkout')->post('/checkout', ['name' => 'X', 'phone' => '01001234567']);

        $response->assertSessionHasErrors('cart');
        $message = session('errors')->first('cart');

        $this->assertStringNotContainsString('available_quantity', (string) $message);
        $this->assertStringNotContainsString((string) self::SECRET_STOCK, (string) $message);
    }
}
