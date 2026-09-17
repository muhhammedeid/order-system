<?php

namespace App\Http\Controllers;

use App\Enums\PriceVisibility;
use App\Models\Category;
use App\Models\Product;
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
}
