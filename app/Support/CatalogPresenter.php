<?php

namespace App\Support;

use App\Enums\PriceVisibility;
use App\Models\Product;

class CatalogPresenter
{
    /**
     * Public card payload. Hidden prices are never included.
     * Expects `category`, `images` and `variants` relations to be loaded.
     */
    public static function productCard(Product $product): array
    {
        $isPublic = $product->price_visibility === PriceVisibility::PublicPrice;
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $images = $product->relationLoaded('images') ? $product->images : collect();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'product_code' => $product->product_code,
            'price_visibility' => $product->price_visibility->value,
            'price' => $isPublic ? $product->price : null,
            'category' => $product->category?->only('id', 'name'),
            'image' => $images->first()->image_path ?? null,
            'images_count' => $images->count(),
            'colors_count' => $variants->pluck('color')->unique()->count(),
        ];
    }
}
