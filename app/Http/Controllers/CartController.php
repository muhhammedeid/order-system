<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Support\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
            'quantity' => ['required', 'integer', 'min:1', 'max:'.OrderItem::MAX_QUANTITY],
        ], [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
        ]);

        $variant = ProductVariant::query()
            ->with('product:id,name,active')
            ->find($validated['variant_id']);

        if (! $variant || ! $variant->product->active) {
            return back()->withErrors(['variant_id' => 'هذا المنتج غير متوفر حاليًا']);
        }

        Cart::add($variant->id, $validated['quantity']);

        return back()->with('success', "تمت إضافة {$variant->product->name} إلى الطلب");
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.OrderItem::MAX_QUANTITY],
        ], [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
        ]);

        if ($validator->fails()) {
            $variantId = $request->integer('variant_id');

            if ($variantId > 0 && $validator->errors()->has('quantity')) {
                $validator->errors()->add('quantity_variant', (string) $variantId);
            }

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $variant = ProductVariant::query()->find($validated['variant_id']);

        if (! $variant) {
            Cart::remove((int) $validated['variant_id']);

            return back();
        }

        Cart::update($variant->id, $validated['quantity']);

        return back();
    }

    public function remove(Request $request)
    {
        $validated = $request->validate(['variant_id' => ['required', 'integer']]);

        Cart::remove((int) $validated['variant_id']);

        return back()->with('info', 'تم حذف المنتج من الطلب');
    }

    public function clear()
    {
        Cart::clear();

        return back()->with('info', 'تم إفراغ الطلب');
    }
}
