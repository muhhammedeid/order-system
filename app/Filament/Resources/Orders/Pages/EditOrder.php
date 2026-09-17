<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('confirm', 'تأكيد الطلب', fn (Order $order) => $order->confirm())
                ->visible(fn (Order $order) => $order->status === \App\Enums\OrderStatus::New)
                ->requiresConfirmation()
                ->modalDescription('سيتم خصم الكميات المطلوبة من الكمية المتاحة لكل مقاس بعد التحقق منها. يمكن إلغاء الطلب المؤكد لاحقًا لاستعادة الكميات.'),
            $this->statusAction('markExported', 'تحديد كمُصدَّر', fn (Order $order) => $order->markExported())
                ->visible(fn (Order $order) => $order->status === \App\Enums\OrderStatus::Confirmed)
                ->requiresConfirmation(),
            $this->statusAction('cancel', 'إلغاء الطلب', fn (Order $order) => $order->cancel())
                ->visible(fn (Order $order) => in_array($order->status, [\App\Enums\OrderStatus::New, \App\Enums\OrderStatus::Confirmed], true))
                ->requiresConfirmation()
                ->color('danger'),
            ViewAction::make(),
        ];
    }

    private function statusAction(string $name, string $label, \Closure $action): Action
    {
        return Action::make($name)
            ->label($label)
            ->action(function (Order $record, array $data) use ($action) {
                try {
                    $action($record);
                } catch (\RuntimeException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();

                    $this->halt();
                }

                Notification::make()
                    ->title('تم تحديث حالة الطلب')
                    ->success()
                    ->send();
            });
    }
}
