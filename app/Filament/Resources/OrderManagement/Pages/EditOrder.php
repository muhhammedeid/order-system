<?php

namespace App\Filament\Resources\OrderManagement\Pages;

use App\Filament\Resources\OrderManagement\OrderManagementResource;
use App\Filament\Resources\OrderManagement\OrderStatusActions;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderStatusActions::confirm(),
            OrderStatusActions::cancel(),
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->getRecord()->items->map(fn (OrderItem $item): array => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'requested_quantity' => $item->requested_quantity,
        ])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Order $record */
        $items = $data['items'] ?? [];
        unset($data['items'], $data['customer_notes_display']);

        DB::transaction(function () use ($record, $data, $items): void {
            $record->update($data);
            $record->updateItems($items);
        });

        return $record;
    }
}
