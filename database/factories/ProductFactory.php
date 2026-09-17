<?php

namespace Database\Factories;

use App\Enums\PriceVisibility;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'product_code' => fake()->unique()->bothify('SH-####'),
            'name' => $name,
            'slug' => str($name)->slug(),
            'price_visibility' => PriceVisibility::PublicPrice,
            'price' => fake()->numberBetween(50, 900),
            'active' => true,
        ];
    }

    public function requestPrice(): static
    {
        return $this->state(fn () => [
            'price_visibility' => PriceVisibility::RequestPrice,
            'price' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
