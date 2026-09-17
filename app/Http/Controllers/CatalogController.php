<?php

namespace App\Http\Controllers;

use App\Enums\PriceVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\VariantColor;
use App\Models\VariantSize;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->where('active', true)
            ->when($request->query('search'), function ($query, $search) {
                $search = trim($search);

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            })
            ->when($request->query('category'), fn ($query, $category) => $query->where('category_id', (int) $category))
            ->with([
                'category:id,name',
                'images' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        $products->getCollection()->each(function (Product $product) {
            if ($product->price_visibility === PriceVisibility::RequestPrice) {
                $product->price = null;
            }
        });

        return Inertia::render('Catalog/Index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'category' => $request->query('category'),
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

        return Inertia::render('Catalog/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'product_code' => $product->product_code,
                'description' => $product->description,
                'category' => $product->category?->only('id', 'name'),
                'price_visibility' => $product->price_visibility->value,
                'price' => $product->price_visibility === PriceVisibility::PublicPrice ? $product->price : null,
                'images' => $product->images->map(fn ($image) => $image->image_path)->values()->all(),
            ],
            'variants' => $this->variantProps($product),
            'whatsapp' => $this->whatsappProps($product),
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
            . "Code: {$product->product_code}\n"
            . 'Product Link: ' . route('product.show', ['product' => $product->slug]);

        return [
            'number' => $number,
            'message' => $message,
            'href' => "https://wa.me/{$number}?text=" . rawurlencode($message),
        ];
    }

    private function variantProps(Product $product): array
    {
        $colorOrder = VariantColor::query()->pluck('sort_order', 'name');
        $sizeOrder = VariantSize::query()->pluck('sort_order', 'name');

        return $product->variants
            ->groupBy('color')
            ->map(fn ($variants, $color) => [
                'color' => $color,
                'sort' => $colorOrder->get($color),
                'sizes' => $variants
                    ->sortBy(fn ($variant) => $sizeOrder->get($variant->size))
                    ->map(fn ($variant) => [
                        'id' => $variant->id,
                        'size' => $variant->size,
                        'available_quantity' => (int) $variant->available_quantity,
                    ])
                    ->values()
                    ->all(),
            ])
            ->sortBy(fn ($group) => [$group['sort'] ?? PHP_INT_MAX, $group['color']])
            ->values()
            ->all();
    }
}
