<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
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
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id', 'required_without:variant_ids'],
            'variant_ids' => ['nullable', 'array', 'min:1', 'max:100', 'required_without:variant_id'],
            'variant_ids.*' => ['integer', 'distinct', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.OrderItem::MAX_QUANTITY],
        ], [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
            'variant_ids.required_without' => 'اختر لونًا واحدًا على الأقل',
        ]);

        $requestedIds = $validated['variant_ids'] ?? [(int) $validated['variant_id']];
        $variantIds = $this->canonicalVariantIds($requestedIds);
        $variant = ProductVariant::query()->with('product:id,name,active')->find($variantIds[0]);

        Cart::add($variantIds, $validated['quantity']);

        return back()->with('success', "تمت إضافة {$variant->product->name} إلى الطلب");
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line_id' => ['nullable', 'string', 'max:64', 'required_without:variant_id'],
            'variant_id' => ['nullable', 'integer', 'required_without:line_id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.OrderItem::MAX_QUANTITY],
        ], [
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
        ]);

        if ($validator->fails()) {
            $identifier = (string) ($request->input('line_id') ?: $request->input('variant_id'));

            if ($identifier !== '' && $validator->errors()->has('quantity')) {
                $validator->errors()->add('quantity_line', $identifier);
            }

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        Cart::update($validated['line_id'] ?? (int) $validated['variant_id'], $validated['quantity']);

        return back();
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'line_id' => ['nullable', 'string', 'max:64', 'required_without:variant_id'],
            'variant_id' => ['nullable', 'integer', 'required_without:line_id'],
        ]);

        Cart::remove($validated['line_id'] ?? (int) $validated['variant_id']);

        return back()->with('info', 'تم حذف المنتج من الطلب');
    }

    public function clear()
    {
        Cart::clear();

        return back()->with('info', 'تم إفراغ الطلب');
    }

    /** @param array<int, mixed> $requestedIds
     *  @return array<int, int>
     */
    private function canonicalVariantIds(array $requestedIds): array
    {
        $requestedIds = array_values(array_unique(array_map('intval', $requestedIds)));
        $requested = ProductVariant::query()
            ->whereIn('id', $requestedIds)
            ->with('product')
            ->get();

        if ($requested->count() !== count($requestedIds) || $requested->pluck('product_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'variant_ids' => 'اختيارات الألوان والمقاسات غير صالحة.',
            ]);
        }

        /** @var Product $product */
        $product = $requested->first()->product;

        if (! $product->active) {
            throw ValidationException::withMessages(['variant_ids' => 'هذا المنتج غير متوفر حاليًا']);
        }

        if (! $product->color_enabled) {
            return $product->variants()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $colors = $requested->pluck('color')->filter()->unique()->values();

        if ($colors->isEmpty()) {
            throw ValidationException::withMessages(['variant_ids' => 'اختر لونًا واحدًا على الأقل']);
        }

        if ($product->size_enabled) {
            $sizes = $requested->pluck('size')->filter()->unique()->values();

            if ($sizes->count() !== 1) {
                throw ValidationException::withMessages(['variant_ids' => 'اختر مقاسًا واحدًا متاحًا لكل الألوان المحددة']);
            }

            $size = $sizes->first();
            $canonical = $product->variants()
                ->whereIn('color', $colors)
                ->where('size', $size)
                ->orderBy('id')
                ->get();

            if ($canonical->pluck('color')->unique()->count() !== $colors->count()) {
                throw ValidationException::withMessages(['variant_ids' => 'المقاس المحدد غير متاح في كل الألوان المختارة']);
            }

            return $canonical->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $product->variants()
            ->whereIn('color', $colors)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}

