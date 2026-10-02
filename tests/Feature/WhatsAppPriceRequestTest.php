<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WhatsAppPriceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_action_contains_configured_number_and_product_data(): void
    {
        Setting::set('whatsapp_number', '+20 123 456 7890');

        $product = Product::factory()->requestPrice()->create([
            'name' => 'Secret Shoe',
            'product_code' => 'SH-9999',
        ]);

        $response = $this->get("/product/{$product->slug}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp.number', '201234567890')
            ->where('whatsapp.message', fn ($message) => str_contains($message, 'Secret Shoe')
                && str_contains($message, 'Code: SH-9999')
                && str_contains($message, "Product Link: http://localhost/product/{$product->slug}"))
            ->where('whatsapp.href', fn ($href) => str_starts_with($href, 'https://wa.me/201234567890?text=')
                && str_contains($href, rawurlencode('Secret Shoe'))));
    }

    public function test_whatsapp_action_is_absent_when_number_not_configured(): void
    {
        $product = Product::factory()->requestPrice()->create();

        $response = $this->get("/product/{$product->slug}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp', null));

        $this->assertStringNotContainsString('wa.me', $response->getContent());
    }

    public function test_whatsapp_action_is_not_rendered_for_public_products(): void
    {
        Setting::set('whatsapp_number', '201234567890');

        $product = Product::factory()->create(['price_visibility' => 'public']);

        $response = $this->get("/product/{$product->slug}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp', null));

        $this->assertStringNotContainsString('wa.me', $response->getContent());
    }
}
