<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Support\Cart;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const ORDER_NUMBER_ATTEMPTS = 5;

    public function checkout(): Response
    {
        return Inertia::render('Checkout/Index', Cart::hydrated());
    }

    public function store()
    {
        $customerData = $this->validateCustomerData();

        if (! count(Cart::items())) {
            return back()->withErrors(['cart' => 'السلة فارغة']);
        }

        $order = $this->createOrderWithRetry($customerData);

        Cart::clear();

        return redirect()->route('order.success', ['order_number' => $order->order_number]);
    }

    public function success(string $orderNumber): Response
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return Inertia::render('Order/Success', [
            'order_number' => $order->order_number,
        ]);
    }

    private function validateCustomerData(): array
    {
        return request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'customer_notes' => ['nullable', 'string', 'max:65535'],
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الموبايل مطلوب',
        ]);
    }

    private function createOrderWithRetry(array $customerData): Order
    {
        for ($attempt = 1; $attempt <= self::ORDER_NUMBER_ATTEMPTS; $attempt++) {
            try {
                return $this->createOrder($customerData);
            } catch (QueryException $exception) {
                if ($this->isDuplicateOrderNumber($exception)) {
                    continue;
                }

                throw $exception;
            }
        }

        throw ValidationException::withMessages([
            'cart' => 'تعذر إنشاء رقم الطلب، حاول مرة أخرى',
        ]);
    }

    private function createOrder(array $customerData): Order
    {
        return DB::transaction(function () use ($customerData) {
            $cart = Cart::hydrated();
            $cartItems = $cart['items'];

            if (! count($cartItems)) {
                throw ValidationException::withMessages(['cart' => 'السلة فارغة']);
            }

            $variants = ProductVariant::query()
                ->whereIn('id', array_column($cartItems, 'variant_id'))
                ->with(['product:id,product_code,name,price_visibility,price,active'])
                ->get()
                ->keyBy('id');

            foreach ($cartItems as $item) {
                $variant = $variants->get($item['variant_id']);

                if (! $variant) {
                    throw ValidationException::withMessages(['cart' => 'أحد المنتجات في الطلب لم يعد متوفرًا']);
                }

                if (! $variant->product->active) {
                    throw ValidationException::withMessages(['cart' => "المنتج {$variant->product->name} غير متوفر حاليًا"]);
                }

                if ($item['quantity'] < 1) {
                    throw ValidationException::withMessages(['cart' => 'الكمية يجب أن تكون أكبر من صفر']);
                }
            }

            $customer = Customer::matchOrCreate($customerData);

            $order = Order::create([
                'order_number' => Order::nextOrderNumber(),
                'customer_id' => $customer->id,
                'customer_notes' => $customerData['customer_notes'] ?? null,
                'total_quantity' => $cart['total_quantity'],
            ]);

            foreach ($cartItems as $item) {
                $variant = $variants->get($item['variant_id']);

                OrderItem::create(
                    OrderItem::snapshotFromVariant($variant) + [
                        'order_id' => $order->id,
                        'quantity' => $item['quantity'],
                    ]
                );
            }

            return $order;
        });
    }

    private function isDuplicateOrderNumber(QueryException $exception): bool
    {
        $errorCode = $exception->errorInfo[1] ?? null;

        return $errorCode === 1062
            && str_contains($exception->getMessage(), 'orders_order_number_unique');
    }
}
