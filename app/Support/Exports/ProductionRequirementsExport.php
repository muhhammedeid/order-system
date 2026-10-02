<?php

namespace App\Support\Exports;

use App\Models\OrderItemColorQuantity;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Production Requirements export at the approved grain: one row per ordered
 * color that still requires production, with that color's requested,
 * delivered and remaining quantities. Quantities come exclusively from the
 * immutable order snapshots, never from the product's current colors, and
 * different color requirements are never summed into one manufactured
 * quantity.
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
            ->reorder()
            ->orderBy('order_items.order_id')
            ->orderBy('order_item_color_quantities.id');
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
            'Requested Qty',
            'Delivered Qty',
            'Remaining Qty',
            'Status',
        ];
    }

    /**
     * @param  OrderItemColorQuantity  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        $order = $record->orderItem?->order;

        return [
            $order?->order_number,
            $this->localDateTime($order?->created_at),
            $order?->customer?->customer_code,
            $order?->customer?->name,
            $order?->customer?->phone,
            $record->product_code,
            $record->product_name,
            $record->color,
            $record->size,
            $record->requested_quantity,
            $record->delivered_quantity,
            $record->remaining_quantity,
            $order?->status?->label(),
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
