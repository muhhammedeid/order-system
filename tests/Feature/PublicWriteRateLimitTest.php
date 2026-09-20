<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWriteRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_submissions_are_rate_limited_per_minute(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post('/checkout', [])->assertRedirect();
        }

        $this->post('/checkout', [])->assertStatus(429);
    }

    public function test_cart_writes_are_rate_limited_per_minute(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->post('/cart/clear')->assertRedirect();
        }

        $this->post('/cart/clear')->assertStatus(429);
    }
}
