<?php

namespace App\Support\Exports;

use App\Models\OrderItem;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Production Requirements export at the approved unit: every quantity is
 * the quantity per color (the requested quantity), never the physical
 * piece total. Delivered and remaining are the same per-color unit.
 */
class ProductionRequirementsExport extends BusinessExport
{
    public function __construct(Builder $query, protected string $filePrefix = 'production-requirements')
    {
        parent::__construct($query);
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
            'Required Qty Per Color',
            'Delivered Per Color',
            'Remaining Per Color',
            'Status',
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
            $record->requested_quantity,
            $record->delivered_quantity_per_color,
            $record->remaining_quantity_per_color,
            $order->status->label(),
        ];
    }

    public function title(): string
    {
        return 'Production Requirements';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName($this->filePrefix);
    }
}
