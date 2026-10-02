<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_code' => fake()->unique()->bothify('C###'),
            'name' => fake()->unique()->name(),
            'company_name' => fake()->company(),
            'phone' => fake()->unique()->numerify('01#########'),
            'whatsapp' => null,
            'governorate' => null,
            'city' => null,
            'address' => null,
            'notes' => null,
        ];
    }

    public function withoutCode(): static
    {
        return $this->state(fn () => ['customer_code' => null]);
    }
}
