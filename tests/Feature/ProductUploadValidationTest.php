<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_svg_upload_is_rejected_before_category_is_saved(): void
    {
        Storage::fake('public');
        $upload = UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        Livewire::actingAs(User::factory()->create())->test(CreateCategory::class)
            ->fillForm(['name' => 'Unsafe', 'slug' => 'sec-svg', 'image_path' => ['upload' => $upload]])
            ->call('create')->assertHasFormErrors(['image_path']);
        $this->assertDatabaseMissing('categories', ['slug' => 'sec-svg']);
        $this->assertSame([], Storage::disk('public')->allFiles('categories/images'));
    }

    public function test_oversized_raster_upload_is_rejected_before_storage(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf1sAAAAASUVORK5CYII=');
        $upload = UploadedFile::fake()->createWithContent('large.png', $png.str_repeat('x', 5121 * 1024));
        Livewire::actingAs(User::factory()->create())->test(CreateProduct::class)
            ->fillForm(['product_code' => 'SEC-SIZE', 'name' => 'Shoe', 'slug' => 'sec-size', 'price' => 10,
                'images' => [['image_path' => ['upload' => $upload], 'sort_order' => 0]]])
            ->call('create')->assertHasFormErrors(['images.0.image_path']);
        $this->assertDatabaseMissing('products', ['product_code' => 'SEC-SIZE']);
        $this->assertSame([], Storage::disk('public')->allFiles('products/images'));
    }

    public function test_svg_upload_is_rejected_before_product_is_saved(): void
    {
        Storage::fake('public');
        $upload = UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        Livewire::actingAs(User::factory()->create())->test(CreateProduct::class)
            ->fillForm(['product_code' => 'SEC-SVG', 'name' => 'Shoe', 'slug' => 'sec-svg', 'price' => 10,
                'images' => [['image_path' => ['upload' => $upload], 'sort_order' => 0]]])
            ->call('create')->assertHasFormErrors(['images.0.image_path']);
        $this->assertDatabaseMissing('products', ['product_code' => 'SEC-SVG']);
        $this->assertSame([], Storage::disk('public')->allFiles('products/images'));
    }
}
