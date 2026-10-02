<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogPresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->where('active', true)
            ->with([
                'category:id,name',
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'variants:id,product_id,color',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => CatalogPresenter::productCard($product))
            ->all();

        $categories = Category::query()
            ->where('active', true)
            ->withCount(['products as products_count' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values();

        return Inertia::render('Home', [
            'products' => $products,
            'categories' => $categories,
            'stats' => [
                'products' => Product::query()->where('active', true)->count(),
                'categories' => $categories->count(),
            ],
            'meta' => [
                'title' => 'الرئيسية',
                'description' => 'MAI SHOES — منصة طلبات الجملة للأحذية. تصفح المنتجات، اعرف الألوان والمقاسات والكميات المتاحة، وأرسل طلبك في دقائق.',
            ],
        ]);
    }
}
