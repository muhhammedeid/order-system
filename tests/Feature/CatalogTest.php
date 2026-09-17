<?php

namespace Tests\Feature;

use App\Enums\PriceVisibility;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_products_are_visible(): void
    {
        $active = Product::factory()->create(['name' => 'Active Shoe']);
        Product::factory()->inactive()->create(['name' => 'Hidden Shoe']);

        $this->get('/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Index')
                ->has('products.data', 1)
                ->where('products.data.0.name', $active->name)
                ->where('products.data.0.product_code', $active->product_code));
    }

    public function test_request_price_product_never_exposes_its_price(): void
    {
        Product::factory()->create([
            'name' => 'Public Shoe',
            'price_visibility' => 'public',
            'price' => 450,
        ]);

        Product::factory()->requestPrice()->create([
            'name' => 'Secret Shoe',
            'price' => 777.55,
        ]);

        $response = $this->get('/catalog');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.data.0.price', null)
            ->where('products.data.1.price', '450.00'));

        $this->assertStringNotContainsString('777.55', $response->getContent());
        $this->assertStringContainsString('450.00', $response->getContent());
    }

    public function test_search_matches_name_or_product_code(): void
    {
        Product::factory()->create(['name' => 'Running Shoe', 'product_code' => 'SH-100']);
        Product::factory()->create(['name' => 'Formal Shoe', 'product_code' => 'FR-200']);

        $this->get('/catalog?search=Running')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Running Shoe')
                ->where('filters.search', 'Running'));

        $this->get('/catalog?search=FR-200')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Formal Shoe'));
    }

    public function test_category_filter_returns_only_that_category(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        Product::factory()->for($category)->create(['name' => 'Category Shoe']);
        Product::factory()->for($otherCategory)->create(['name' => 'Other Shoe']);
        Product::factory()->create(['name' => 'No Category Shoe']);

        $this->get('/catalog?category='.$category->getKey())
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Category Shoe'));
    }

    public function test_catalog_is_paginated(): void
    {
        Product::factory()->count(30)->create();

        $this->get('/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 24)
                ->where('products.current_page', 1)
                ->where('products.last_page', 2));

        $this->get('/catalog?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 6)
                ->where('products.current_page', 2));
    }

    public function test_inactive_categories_are_not_listed_as_filters(): void
    {
        $active = Category::factory()->create(['name' => 'Active Cat']);
        Category::factory()->create(['name' => 'Inactive Cat', 'active' => false]);

        $this->get('/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Active Cat'));
    }
}
