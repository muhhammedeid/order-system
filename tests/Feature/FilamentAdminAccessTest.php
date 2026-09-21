<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\VariantColors\VariantColorResource;
use App\Filament\Resources\VariantSizes\VariantSizeResource;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_login_page_renders(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_admin_can_authenticate_and_reach_the_filament_dashboard(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertStatus(200);
    }

    public function test_admin_panel_renders_right_to_left(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_admin_panel_follows_the_selected_english_locale(): void
    {
        $admin = User::factory()->create();

        $this->withSession(['locale' => 'en'])
            ->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Order Management');
    }

    public function test_admin_resource_pages_render(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/products')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/products/create')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/categories')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/categories/create')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/variant-colors')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/variant-sizes')->assertStatus(200);

        $product = Product::factory()->create();

        $this->actingAs($admin)->get("/admin/products/{$product->getKey()}/edit")->assertStatus(200);
        $this->actingAs($admin)->get('/admin/settings')->assertStatus(200);
    }

    public function test_customer_edit_page_renders_without_missing_icons(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->getKey()}/edit")
            ->assertOk();
    }

    public function test_products_list_exposes_the_create_action(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListProducts::class)
            ->assertActionExists('create')
            ->assertSee('إضافة منتج');
    }

    public function test_variant_lookups_and_settings_share_the_settings_navigation_group(): void
    {
        $this->assertSame('الإعدادات', VariantColorResource::getNavigationGroup());
        $this->assertSame('ألوان الأصناف', VariantColorResource::getNavigationLabel());
        $this->assertSame('الإعدادات', VariantSizeResource::getNavigationGroup());
        $this->assertSame('مقاسات الأصناف', VariantSizeResource::getNavigationLabel());
        $this->assertSame('الإعدادات', Settings::getNavigationGroup());
        $this->assertSame('الإعدادات العامة', Settings::getNavigationLabel());
    }
}
