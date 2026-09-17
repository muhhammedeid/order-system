<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Imports\HeaderContractException;
use App\Support\Imports\ImportResult;
use App\Support\Imports\ProductsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private const ROW = [
        'Product Code' => '',
        'Product Name' => '',
        'Category' => '',
        'Price' => '',
        'Price Visibility' => '',
        'Active' => '',
    ];

    private function import(array $rows): ImportResult
    {
        $keyed = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;
            $keyed[$rowNumber] = array_merge(self::ROW, $row);
        }

        return (new ProductsImporter)->process($keyed);
    }

    public function test_creates_product_with_new_code(): void
    {
        $result = $this->import([[
            'Product Code' => 'SH-100',
            'Product Name' => 'Imported Sneaker',
            'Category' => 'Men Shoes',
            'Price' => '450',
            'Price Visibility' => 'public',
            'Active' => 'yes',
        ]]);

        $this->assertSame(1, $result->created());

        $product = Product::query()->where('product_code', 'SH-100')->firstOrFail();

        $this->assertSame('Imported Sneaker', $product->name);
        $this->assertSame('imported-sneaker', $product->slug);
        $this->assertSame('450.00', $product->price);
        $this->assertSame('public', $product->price_visibility->value);
        $this->assertTrue($product->active);
        $this->assertSame('Men Shoes', $product->category->name);
    }

    public function test_updates_existing_product_by_code_and_preserves_slug(): void
    {
        $existing = Product::factory()->create([
            'product_code' => 'SH-100',
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        $result = $this->import([[
            'Product Code' => 'SH-100',
            'Product Name' => 'Renamed Sneaker',
            'Category' => 'Men Shoes',
            'Price' => '500',
            'Price Visibility' => 'public',
            'Active' => '0',
        ]]);

        $this->assertSame(1, $result->updated());

        $existing->refresh();

        $this->assertSame('Renamed Sneaker', $existing->name);
        $this->assertSame('old-name', $existing->slug, 'existing slug must be preserved');
        $this->assertSame('500.00', $existing->price);
        $this->assertFalse($existing->active);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_new_product_slug_collision_is_rejected(): void
    {
        Product::factory()->create(['name' => 'Imported Sneaker', 'slug' => 'imported-sneaker']);

        $result = $this->import([[
            'Product Code' => 'SH-200',
            'Product Name' => 'Imported Sneaker',
            'Category' => 'Men Shoes',
            'Price' => '450',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('رابطًا مستخدمًا', $result->invalidDescriptions()[0]);
        $this->assertDatabaseMissing('products', ['product_code' => 'SH-200']);
    }

    public function test_duplicate_product_code_within_file_is_rejected(): void
    {
        $result = $this->import([
            ['Product Code' => 'SH-100', 'Product Name' => 'First Shoe', 'Category' => 'C', 'Price' => '100', 'Price Visibility' => 'public', 'Active' => '1'],
            ['Product Code' => 'SH-100', 'Product Name' => 'Second Shoe', 'Category' => 'C', 'Price' => '200', 'Price Visibility' => 'public', 'Active' => '1'],
        ]);

        $this->assertSame(1, $result->created());
        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('مكرر', $result->invalidDescriptions()[0]);

        $product = Product::query()->where('product_code', 'SH-100')->firstOrFail();
        $this->assertSame('First Shoe', $product->name);
    }

    public function test_public_price_is_required_and_non_negative(): void
    {
        $result = $this->import([[
            'Product Code' => 'SH-100',
            'Product Name' => 'No Price Shoe',
            'Category' => 'Men Shoes',
            'Price' => '',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('Price مطلوب', $result->invalidDescriptions()[0]);

        $result = $this->import([[
            'Product Code' => 'SH-101',
            'Product Name' => 'Negative Price Shoe',
            'Category' => 'Men Shoes',
            'Price' => '-5',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('صفرًا أو أكثر', $result->invalidDescriptions()[0]);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_request_price_can_store_an_internal_price(): void
    {
        $result = $this->import([[
            'Product Code' => 'SH-300',
            'Product Name' => 'Secret Priced Shoe',
            'Category' => 'Men Shoes',
            'Price' => '700.50',
            'Price Visibility' => 'request_price',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->created());

        $product = Product::query()->where('product_code', 'SH-300')->firstOrFail();

        $this->assertSame('request_price', $product->price_visibility->value);
        $this->assertSame('700.50', $product->price);
    }

    public function test_blank_request_price_price_does_not_erase_existing_price(): void
    {
        $existing = Product::factory()->requestPrice()->create([
            'product_code' => 'SH-300',
            'name' => 'Secret Shoe',
            'slug' => 'secret-shoe',
            'price' => 700.50,
        ]);

        $result = $this->import([[
            'Product Code' => 'SH-300',
            'Product Name' => 'Secret Shoe',
            'Category' => 'Men Shoes',
            'Price' => '',
            'Price Visibility' => 'request_price',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->updated());

        $existing->refresh();

        $this->assertSame('700.50', $existing->price);
    }

    public function test_active_parsing(): void
    {
        $result = $this->import([
            ['Product Code' => 'SH-A', 'Product Name' => 'Shoe A', 'Category' => 'C', 'Price' => '1', 'Price Visibility' => 'public', 'Active' => 'TRUE'],
            ['Product Code' => 'SH-B', 'Product Name' => 'Shoe B', 'Category' => 'C', 'Price' => '1', 'Price Visibility' => 'public', 'Active' => 'no'],
            ['Product Code' => 'SH-C', 'Product Name' => 'Shoe C', 'Category' => 'C', 'Price' => '1', 'Price Visibility' => 'public', 'Active' => 'maybe'],
        ]);

        $this->assertSame(2, $result->created());
        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('Active', $result->invalidDescriptions()[0]);

        $this->assertTrue(Product::query()->where('product_code', 'SH-A')->first()->active);
        $this->assertFalse(Product::query()->where('product_code', 'SH-B')->first()->active);
        $this->assertDatabaseMissing('products', ['product_code' => 'SH-C']);
    }

    public function test_category_is_reused_when_existing(): void
    {
        $category = Category::factory()->create(['name' => 'Men Shoes']);

        $this->import([[
            'Product Code' => 'SH-100',
            'Product Name' => 'Shoe A',
            'Category' => 'Men Shoes',
            'Price' => '1',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertSame(1, Category::query()->count());
        $this->assertSame($category->id, Product::query()->where('product_code', 'SH-100')->first()->category_id);
    }

    public function test_missing_category_is_created(): void
    {
        $this->import([[
            'Product Code' => 'SH-100',
            'Product Name' => 'Shoe A',
            'Category' => 'Women Shoes',
            'Price' => '1',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertDatabaseHas('categories', ['name' => 'Women Shoes', 'active' => true]);
    }

    public function test_failed_product_row_leaves_no_orphan_category(): void
    {
        $this->import([[
            'Product Code' => 'SH-100',
            'Product Name' => 'Shoe A',
            'Category' => 'Orphan Category',
            'Price' => 'not-a-number',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertDatabaseMissing('categories', ['name' => 'Orphan Category']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_import_never_creates_or_modifies_variants(): void
    {
        $existing = Product::factory()->create([
            'product_code' => 'SH-100',
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);
        $existing->variants()->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 5]);

        $this->import([
            ['Product Code' => 'SH-100', 'Product Name' => 'New Name', 'Category' => 'C', 'Price' => '10', 'Price Visibility' => 'public', 'Active' => '1'],
            ['Product Code' => 'SH-200', 'Product Name' => 'Brand New', 'Category' => 'C', 'Price' => '20', 'Price Visibility' => 'request_price', 'Active' => '1'],
        ]);

        $this->assertSame(1, ProductVariant::query()->count());
        $this->assertDatabaseHas('product_variants', ['color' => 'Black', 'size' => '41']);
        $this->assertNull(Product::query()->where('product_code', 'SH-200')->first()->variants()->first());
    }

    public function test_header_contract_is_enforced(): void
    {
        $reader = new \App\Support\Imports\RawSheetReader;
        $reader->rows = [
            ['Product Code', 'Product Name', 'Category', 'Price', 'Active'],
        ];

        $this->expectException(HeaderContractException::class);
        $this->expectExceptionMessage('Price Visibility');

        $reader->keyedRows(ProductsImporter::HEADERS);
    }
}
