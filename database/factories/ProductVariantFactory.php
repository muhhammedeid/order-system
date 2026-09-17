<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'color' => fake()->randomElement(['Black', 'White', 'Brown', 'Navy']),
            'size' => fake()->numberBetween(38, 45),
            'available_quantity' => fake()->numberBetween(0, 50),
        ];
    }
}
