<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_home_lists_latest_active_products(): void
    {
        Product::factory()->create(['name' => 'Visible Home Shoe']);
        Product::factory()->inactive()->create(['name' => 'Hidden Home Shoe']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('products', 1)
            ->where('products.0.name', 'Visible Home Shoe'));
    }

    public function test_home_never_exposes_request_price_values(): void
    {
        Product::factory()->requestPrice()->create(['name' => 'Secret Home Shoe', 'price' => 654.32]);

        $response = $this->get('/');

        $this->assertStringNotContainsString('654.32', $response->getContent());
    }

    public function test_home_lists_only_categories_with_active_products(): void
    {
        $withProducts = Category::factory()->create(['name' => 'Populated Cat']);
        Category::factory()->create(['name' => 'Empty Cat']);

        Product::factory()->for($withProducts)->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Populated Cat'));
    }
}
