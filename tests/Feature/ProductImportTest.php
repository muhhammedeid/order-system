<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantColor;
use App\Models\VariantSize;
use App\Support\Imports\HeaderContractException;
use App\Support\Imports\ImportResult;
use App\Support\Imports\ImportRunner;
use App\Support\Imports\ProductsImporter;
use App\Support\Imports\RawSheetReader;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function test_existing_request_price_product_cannot_become_public_without_a_price(): void
    {
        $existing = Product::factory()->requestPrice()->create([
            'product_code' => 'SH-300',
            'name' => 'Secret Shoe',
            'slug' => 'secret-shoe',
            'price' => null,
        ]);

        $result = $this->import([[
            'Product Code' => 'SH-300',
            'Product Name' => 'Secret Shoe',
            'Category' => 'Men Shoes',
            'Price' => '',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('Price مطلوب', $result->invalidDescriptions()[0]);

        $existing->refresh();

        $this->assertSame('request_price', $existing->price_visibility->value);
        $this->assertNull($existing->price);
    }

    public function test_existing_request_price_product_can_become_public_with_a_valid_price(): void
    {
        $existing = Product::factory()->requestPrice()->create([
            'product_code' => 'SH-300',
            'name' => 'Secret Shoe',
            'slug' => 'secret-shoe',
            'price' => null,
        ]);

        $result = $this->import([[
            'Product Code' => 'SH-300',
            'Product Name' => 'Secret Shoe',
            'Category' => 'Men Shoes',
            'Price' => '250',
            'Price Visibility' => 'public',
            'Active' => '1',
        ]]);

        $this->assertSame(1, $result->updated());

        $existing->refresh();

        $this->assertSame('public', $existing->price_visibility->value);
        $this->assertSame('250.00', $existing->price);
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

    public function test_existing_variants_remain_unchanged_and_no_defaults_are_invented_without_settings(): void
    {
        $existing = Product::factory()->create([
            'product_code' => 'SH-100',
            'name' => 'Old Name',
            'slug' => 'old-name',
            'size_enabled' => true,
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

    public function test_import_preserves_existing_color_enabled_flag(): void
    {
        $existing = Product::factory()->create([
            'product_code' => 'SH-300',
            'name' => 'Old Forced Colors Name',
            'slug' => 'old-forced-colors-name',
            'color_enabled' => false,
            'size_enabled' => false,
        ]);
        $existing->variants()->create(['color' => 'Black', 'size' => '41', 'available_quantity' => 5]);

        $this->import([
            ['Product Code' => 'SH-300', 'Product Name' => 'New Name', 'Category' => 'C', 'Price' => '10', 'Price Visibility' => 'public', 'Active' => '1'],
        ]);

        $this->assertFalse($existing->refresh()->color_enabled);
        $this->assertFalse($existing->size_enabled);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $existing->getKey(),
            'color' => 'Black',
            'size' => '41',
        ]);
    }

    public function test_only_product_code_header_is_required(): void
    {
        $reader = new RawSheetReader;
        $reader->rows = [
            ['Product Code'],
            ['00123'],
        ];
        $this->assertSame([2 => ['Product Code' => '00123']], $reader->keyedRows(ProductsImporter::REQUIRED_HEADERS));
        $reader->rows = [['Product Name']];
        $this->expectException(HeaderContractException::class);
        $this->expectExceptionMessage('Product Code');

        $reader->keyedRows(ProductsImporter::REQUIRED_HEADERS);
    }

    public function test_code_only_defaults_use_all_active_options_without_images(): void
    {
        foreach (['Black', 'Coffee'] as $color) {
            VariantColor::create(['name' => $color, 'active' => true]);
        }
        foreach (['37', '38', '39', '40', '41'] as $size) {
            VariantSize::create(['name' => $size, 'active' => true]);
        }
        VariantColor::create(['name' => 'Inactive', 'active' => false]);
        $this->assertSame(1, $this->import([['Product Code' => '00123']])->created());
        $product = Product::where('product_code', '00123')->firstOrFail();
        $this->assertSame('Mai 00123', $product->name);
        $this->assertSame('request_price', $product->price_visibility->value);
        $this->assertNull($product->price);
        $this->assertNull($product->category_id);
        $this->assertFalse($product->color_enabled);
        $this->assertFalse($product->size_enabled);
        $this->assertTrue($product->active);
        $this->assertSame(0, $product->images()->count());
        $this->assertSame(10, $product->variants()->count());
        $this->assertSame(0, $product->variants()->sum('available_quantity'));
    }

    public function test_code_only_update_preserves_manual_fields_images_and_variants(): void
    {
        $product = Product::factory()->create(['product_code' => '00123', 'name' => 'Manual name', 'description' => 'Manual description', 'price' => 123, 'color_enabled' => true, 'size_enabled' => true]);
        $variant = $product->variants()->create(['color' => 'Custom', 'size' => '42', 'available_quantity' => 17]);
        $image = $product->images()->create(['image_path' => 'products/manual.jpg', 'sort_order' => 0]);
        $before = $product->fresh()->getAttributes();
        $this->assertSame(1, $this->import([['Product Code' => '00123']])->updated());
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(17, $variant->fresh()->available_quantity);
        $this->assertSame('products/manual.jpg', $image->fresh()->image_path);
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_explicit_options_add_missing_variants_and_never_reset_existing_quantity(): void
    {
        $product = Product::factory()->create(['product_code' => '00123']);
        $variant = $product->variants()->create(['color' => 'Black', 'size' => '37', 'available_quantity' => 17]);
        $result = $this->import([['Product Code' => '00123', 'Colors' => 'Black,Coffee', 'Sizes' => '37,38', 'Color Enabled' => 'true', 'Size Enabled' => 'true']]);
        $this->assertSame(1, $result->updated());
        $this->assertSame(4, $product->variants()->count());
        $this->assertSame(17, $variant->fresh()->available_quantity);
        $this->assertTrue($product->fresh()->size_enabled);
        $this->assertSame(1, $this->import([['Product Code' => 'BAD', 'Colors' => 'Black,,Coffee', 'Sizes' => '37']])->invalid());
        $this->assertDatabaseMissing('products', ['product_code' => 'BAD']);
    }

    public function test_official_template_preserves_text_codes_and_imports_through_the_runner(): void
    {
        $sheet = IOFactory::load(resource_path('templates/products-import.xlsx'));
        $this->assertSame(1, $sheet->getSheetCount());
        $worksheet = $sheet->getActiveSheet();
        $this->assertSame(ProductsImporter::TEMPLATE_HEADERS, $worksheet->rangeToArray('A1:K1')[0]);
        $this->assertSame('@', $worksheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $worksheet->setCellValueExplicit('A2', '00123', DataType::TYPE_STRING);
        Storage::fake('local');
        $path = 'import-products.xlsx';
        (new Xlsx($sheet))->save(Storage::disk('local')->path($path));
        $result = ImportRunner::products($path);
        $this->assertSame(1, $result->created());
        $this->assertSame(0, $result->invalid());
        $this->assertDatabaseHas('products', ['product_code' => '00123', 'name' => 'Mai 00123']);
    }

    public function test_formula_cells_and_invalid_flag_combinations_are_reported_without_partial_rows(): void
    {
        $result = $this->import([
            ['Product Code' => '=1+1'],
            ['Product Code' => 'BADFLAGS', 'Color Enabled' => '0', 'Size Enabled' => '1'],
            ['Product Code' => 'MISSING', 'Color Enabled' => 'maybe'],
            ['Product Name' => 'Missing code'],
        ]);
        $this->assertSame(4, $result->invalid());
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_admin_can_download_official_import_template(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs(User::factory()->create())
            ->test(ListProducts::class)
            ->callAction('downloadImportTemplate')
            ->assertFileDownloaded('products-import.xlsx');
    }

    public function test_import_update_preserves_order_snapshots(): void
    {
        $product = Product::factory()->create(['product_code' => '00123', 'name' => 'Original name', 'price' => 123, 'color_enabled' => true, 'size_enabled' => true]);
        $variant = $product->variants()->create(['color' => 'Black', 'size' => '37', 'available_quantity' => 0]);
        $this->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => 3]);
        $this->post('/checkout', ['name' => 'Test customer', 'phone' => '01001234567', 'city' => 'Cairo'])->assertRedirect();
        $item = OrderItem::firstOrFail();
        $before = $item->getAttributes();
        $result = $this->import([['Product Code' => '00123', 'Product Name' => 'Updated name', 'Price' => 456, 'Colors' => 'Black,Coffee', 'Sizes' => '37']]);
        $this->assertSame(1, $result->updated());
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertSame('Original name', $item->fresh()->product_name);
        $this->assertSame('123.00', $item->fresh()->unit_price);
    }
}
