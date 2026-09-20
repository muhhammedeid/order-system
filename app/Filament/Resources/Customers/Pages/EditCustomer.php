<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Customer $record, DeleteAction $action): void {
                    if (! $record->isReferencedByOrders()) {
                        return;
                    }

                    Notification::make()
                        ->title('تعذّر حذف العميل')
                        ->body('لا يمكن حذف هذا العميل لأنه مرتبط بطلبات مسجلة في النظام. يمكنك الإبقاء على بيانات العميل دون حذف للحفاظ على سجل الطلبات.')
                        ->danger()
                        ->send();

                    $action->cancel();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return Customer::validate($data);
    }
}
