<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Support\Cart;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cart/Index', Cart::hydrated());
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
        ]);

        $variant = ProductVariant::query()
            ->with('product:id,active')
            ->find($validated['variant_id']);

        if (! $variant || ! $variant->product->active) {
            return back()->withErrors(['variant_id' => 'هذا المنتج غير متوفر حاليًا']);
        }

        if ($validated['quantity'] > $variant->available_quantity) {
            return back()->withErrors([
                'quantity' => 'الكمية المطلوبة أكبر من الكمية المتاحة',
            ]);
        }

        Cart::add($variant->id, $validated['quantity']);

        return redirect()->route('cart.index');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
        ]);

        $variant = ProductVariant::query()->find($validated['variant_id']);

        if (! $variant) {
            Cart::remove((int) $validated['variant_id']);

            return back();
        }

        if ($validated['quantity'] > $variant->available_quantity) {
            return back()->withErrors([
                'quantity' => 'الكمية المطلوبة أكبر من الكمية المتاحة',
            ]);
        }

        Cart::update($variant->id, $validated['quantity']);

        return back();
    }

    public function remove(Request $request)
    {
        $validated = $request->validate(['variant_id' => ['required', 'integer']]);

        Cart::remove((int) $validated['variant_id']);

        return back();
    }

    public function clear()
    {
        Cart::clear();

        return back();
    }
}
