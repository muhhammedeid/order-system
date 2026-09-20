<?php

namespace App\Support\Exports;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

/**
 * Order exports at the approved grain: one row per ordered variant,
 * built from the immutable order-item snapshots.
 */
class OrderItemsExport extends BusinessExport implements WithColumnFormatting
{
    public function __construct(Builder $query, protected string $filePrefix = 'orders')
    {
        parent::__construct($query);
    }

    public static function forOrder(Order $order): self
    {
        return new self(
            OrderItem::query()
                ->where('order_id', $order->getKey())
                ->orderBy('order_id')
                ->orderBy('id'),
            'order-'.$order->order_number,
        );
    }

    public static function forOrders(Builder $orders, string $filePrefix = 'orders'): self
    {
        $orderIds = (clone $orders)->reorder()->select('id')->pluck('id')->all();

        return self::forOrderIds($orderIds, $filePrefix);
    }

    /**
     * @param  iterable<int, int>  $orderIds
     */
    public static function forOrderIds(iterable $orderIds, string $filePrefix = 'orders'): self
    {
        return new self(
            OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->orderBy('order_id')
                ->orderBy('id'),
            $filePrefix,
        );
    }

    public function query(): Builder
    {
        return $this->query
            ->with([
                'order:id,order_number,status,customer_id,created_at',
                'order.customer:id,name,phone,customer_code',
            ])
            ->reorder()
            ->orderBy('order_items.order_id')
            ->orderBy('order_items.id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Order No',
            'Order Date',
            'Customer Code',
            'Customer Name',
            'Phone',
            'Product Code',
            'Product Name',
            'Color',
            'Size',
            'Ordered Qty',
            'Delivered Qty',
            'Remaining Qty',
            'Status',
            'Unit Price',
        ];
    }

    /**
     * @param  OrderItem  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        $order = $record->order;

        return [
            $order->order_number,
            $this->localDateTime($order->created_at),
            $order->customer?->customer_code,
            $order->customer?->name,
            $order->customer?->phone,
            $record->product_code,
            $record->product_name,
            $record->color,
            $record->size,
            $record->quantity,
            $record->delivered_quantity,
            $record->remaining_quantity,
            $order->status->label(),
            $record->unit_price === null ? null : (float) $record->unit_price,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'N' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Orders';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName($this->filePrefix);
    }
}
