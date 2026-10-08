<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Support\Cart;
use App\Support\WhatsApp\Order\OrderStatusNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OrderController extends Controller
{
    private const ORDER_NUMBER_ATTEMPTS = 5;

    public function checkout(): Response
    {
        return Inertia::render('Checkout/Index', Cart::hydrated());
    }

    public function store(StoreOrderRequest $request)
    {
        $customerData = $request->validated();

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

            $variantIds = collect($cartItems)
                ->flatMap(fn (array $item): array => $item['variant_ids'])
                ->unique()
                ->values()
                ->all();

            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->with(['product:id,product_code,name,price_visibility,price,active,size_enabled'])
                ->get()
                ->keyBy('id');

            foreach ($cartItems as $item) {
                $selected = collect($item['variant_ids'])
                    ->map(fn (int $id) => $variants->get($id))
                    ->filter()
                    ->values();
                $variant = $selected->first();

                if ($selected->count() !== count($item['variant_ids'])
                    || $selected->pluck('product_id')->unique()->count() !== 1) {
                    throw ValidationException::withMessages(['cart' => 'أحد المنتجات في الطلب لم يعد متوفرًا']);
                }

                if (! $variant->product->active) {
                    throw ValidationException::withMessages(['cart' => "المنتج {$variant->product->name} غير متوفر حاليًا"]);
                }

                if ($item['quantity'] < 1) {
                    throw ValidationException::withMessages(['cart' => 'الكمية يجب أن تكون أكبر من صفر']);
                }

                $this->validateQuantityDistribution($selected, (int) $item['quantity']);
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
                $snapshot = OrderItem::snapshotFromVariant($variant);
                $snapshot['color'] = $item['color'] !== '' ? $item['color'] : null;
                $snapshot['size'] = $item['size'] !== '' ? $item['size'] : null;

                OrderItem::create(
                    $snapshot + [
                        'order_id' => $order->id,
                        'requested_quantity' => $item['quantity'],
                        'color_count' => $item['color_count'],
                        'quantity' => $item['pieces_quantity'],
                    ]
                );
            }

            // Persist the communication snapshot atomically; queue publication waits for commit.
            $this->notifyOrderPlaced($order);

            return $order;
        });
    }

    private function isDuplicateOrderNumber(QueryException $exception): bool
    {
        $errorCode = $exception->errorInfo[1] ?? null;

        return $errorCode === 1062
            && str_contains($exception->getMessage(), 'orders_order_number_unique');
    }

    private function validateQuantityDistribution($variants, int $quantity): void
    {
        $product = $variants->first()?->product;

        if (! $product) {
            return;
        }

        $colorCount = max(1, $variants->pluck('color')->filter()->unique()->count());

        if ($quantity > intdiv(OrderItem::MAX_QUANTITY, $colorCount)) {
            throw ValidationException::withMessages([
                'cart' => 'إجمالي عدد القطع يتجاوز الحد المسموح.',
            ]);
        }

        if ($product->size_enabled) {
            return;
        }

        $sizeCounts = $variants
            ->groupBy(fn (ProductVariant $variant): string => (string) $variant->color)
            ->map(fn ($group): int => $group->pluck('size')->filter()->unique()->count());

        if ($sizeCounts->isEmpty() || $sizeCounts->contains(0)) {
            throw ValidationException::withMessages([
                'cart' => 'أحد المنتجات لا يحتوي على المقاسات الافتراضية لكل لون.',
            ]);
        }

        foreach ($sizeCounts->unique() as $sizeCount) {
            if ($quantity % $sizeCount !== 0) {
                throw ValidationException::withMessages([
                    'cart' => "يجب أن تقبل كمية المنتج القسمة على عدد المقاسات المتاحة ({$sizeCount}).",
                ]);
            }
        }
    }

    /**
     * Operational order-placed WhatsApp update. Runs after the order was
     * committed and must never affect the customer response.
     */
    private function notifyOrderPlaced(Order $order): void
    {
        try {
            $order->refresh();

            app(OrderStatusNotifier::class)->orderPlaced($order);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp order placed notification failed', [
                'order_id' => $order->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
