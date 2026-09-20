<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('حفظ التغييرات')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->action('save'),
            DeleteAction::make()
                ->before(function (Product $record, DeleteAction $action): void {
                    if (! $record->isReferencedByActiveOrder()) {
                        return;
                    }

                    Notification::make()
                        ->title('تعذّر حذف المنتج')
                        ->body('لا يمكن حذف المنتج لأن بعض مقاساته مرتبطة بطلبات نشطة — يمكن تعطيل المنتج بدلًا من حذفه.')
                        ->danger()
                        ->send();

                    $action->cancel();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Product::validate($data, $this->record);

        return $data;
    }
}
