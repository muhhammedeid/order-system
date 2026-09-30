<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantManagementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_choice_toggles_do_not_remove_availability_data(): void
    {
        $product = Product::factory()->create(['color_enabled' => false, 'size_enabled' => false]);
        $variant = $product->variants()->create([
            'color' => 'Brown',
            'size' => '42',
            'available_quantity' => 30,
        ]);

        $product->update(['color_enabled' => true, 'size_enabled' => true]);

        $this->assertSame('Brown', $variant->refresh()->color);
        $this->assertSame('42', $variant->size);
    }
}
