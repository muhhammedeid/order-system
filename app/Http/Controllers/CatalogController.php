<?php

namespace App\Http\Controllers;

use App\Enums\PriceVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\VariantColor;
use App\Models\VariantSize;
use App\Support\CatalogPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $category = $request->query('category');

        $products = Product::query()
            ->where('active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            })
            ->when($category, fn ($query, $category) => $query->where('category_id', (int) $category))
            ->with([
                'category:id,name',
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'variants:id,product_id,color',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        $products->through(fn (Product $product) => CatalogPresenter::productCard($product));

        return Inertia::render('Catalog/Index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('active', true)
                ->withCount(['products as products_count' => fn ($query) => $query->where('active', true)])
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'category' => $category,
            ],
            'meta' => [
                'title' => $search !== '' ? "بحث: {$search}" : 'المتجر',
                'description' => 'تصفح تشكيلة أحذية MAI SHOES بالجملة مع الألوان والمقاسات والكميات المتاحة لكل منتج.',
            ],
        ]);
    }

    public function show(Product $product): Response
    {
        abort_if(! $product->active, 404);

        $product->load([
            'category:id,name',
            'images' => fn ($query) => $query->orderBy('sort_order'),
            'variants',
        ]);

        $firstImage = $product->images->first();

        return Inertia::render('Catalog/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'product_code' => $product->product_code,
                'description' => $product->description,
                'category' => $product->category?->only('id', 'name'),
                'price_visibility' => $product->price_visibility->value,
                'price' => $product->price_visibility === PriceVisibility::PublicPrice ? $product->price : null,
                'color_enabled' => $product->color_enabled,
                'size_enabled' => $product->size_enabled,
                'images' => $product->images->map(fn ($image) => $image->url())->values()->all(),
            ],
            'variants' => $this->variantProps($product),
            'whatsapp' => $this->whatsappProps($product),
            'related' => $this->relatedProps($product),
            'meta' => [
                'title' => $product->name,
                'description' => $product->description
                    ? mb_substr(strip_tags($product->description), 0, 155)
                    : "اطلب {$product->name} بالجملة من MAI SHOES — كود المنتج {$product->product_code}.",
                'type' => 'product',
                'image' => $firstImage?->url(),
            ],
        ]);
    }

    private function whatsappProps(Product $product): ?array
    {
        if ($product->price_visibility !== PriceVisibility::RequestPrice) {
            return null;
        }

        $number = Setting::whatsappNumber();

        if ($number === null) {
            return null;
        }

        $message = "مرحبًا، أريد معرفة سعر المنتج {$product->name}\n"
            ."Code: {$product->product_code}\n"
            .'Product Link: '.route('product.show', ['product' => $product->slug]);

        return [
            'number' => $number,
            'message' => $message,
            'href' => "https://wa.me/{$number}?text=".rawurlencode($message),
        ];
    }

    private function relatedProps(Product $product): array
    {
        if (! $product->category_id) {
            return [];
        }

        return Product::query()
            ->where('active', true)
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->with([
                'category:id,name',
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'variants:id,product_id,color',
            ])
            ->orderByDesc('id')
            ->limit(4)
            ->get()
            ->map(fn (Product $related) => CatalogPresenter::productCard($related))
            ->all();
    }

    private function variantProps(Product $product): array
    {
        $colorOrder = VariantColor::query()->pluck('sort_order', 'name');
        $sizeOrder = VariantSize::query()->pluck('sort_order', 'name');

        return $product->variants
            ->groupBy('color')
            ->map(fn ($variants, $color) => [
                'color' => filled($color) ? $color : null,
                'sort' => $colorOrder->get($color),
                'sizes' => $variants
                    ->sortBy(fn ($variant) => $sizeOrder->get($variant->size))
                    ->map(fn ($variant) => [
                        'id' => $variant->id,
                        'size' => $variant->size,
                    ])
                    ->values()
                    ->all(),
            ])
            ->sortBy(fn ($group) => [$group['sort'] ?? PHP_INT_MAX, $group['color']])
            ->values()
            ->all();
    }
}
