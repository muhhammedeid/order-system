<?php

namespace App\Filament\Resources\OrderManagement;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use RuntimeException;

/**
 * Operational order actions, shared by the Order Management table, view and
 * review pages. Every transition delegates to the guarded domain methods, so
 * locking, payload validation and status derivation are identical no matter
 * where the admin triggers them.
 */
class OrderStatusActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('تأكيد الطلب')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
            ->modalHeading(fn (Order $record): string => "تأكيد الطلب {$record->order_number}")
            ->modalDescription('سيتم حفظ حالة الطلب كـ «مؤكد» بعد المراجعة. لا يؤثر التأكيد على كمية المخزون، والطلب المؤكد يدخل مرحلة التنفيذ ولا يمكن إلغاؤه.')
            ->modalSubmitActionLabel('تأكيد الطلب')
            ->form([
                Textarea::make('admin_notes')
                    ->label('ملاحظات الإدارة')
                    ->helperText('تُحفظ مع الطلب ويمكن تعديلها لاحقًا.')
                    ->rows(3)
                    ->maxLength(65535)
                    ->default(fn (Order $record): ?string => $record->admin_notes),
            ])
            ->action(function (Order $record, array $data): void {
                if (! self::run($record, fn () => $record->confirm())) {
                    return;
                }

                self::persistNotes($record, $data);

                Notification::make()
                    ->title("تم تأكيد الطلب {$record->order_number}")
                    ->success()
                    ->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('إلغاء الطلب')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
            ->modalHeading(fn (Order $record): string => "إلغاء الطلب {$record->order_number}")
            ->modalDescription('يُستخدم الإلغاء عندما يتعذر تأكيد الطلب مع العميل. لا يمكن التراجع عن الإلغاء، والطلبات المؤكدة لا يمكن إلغاؤها.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->action(function (Order $record): void {
                if (! self::run($record, fn () => $record->cancel())) {
                    return;
                }

                Notification::make()
                    ->title("تم إلغاء الطلب {$record->order_number}")
                    ->success()
                    ->send();
            });
    }

    public static function review(): Action
    {
        return Action::make('review')
            ->label('مراجعة وتأكيد')
            ->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
            ->url(fn (Order $record): string => OrderManagementResource::getUrl('confirm', ['record' => $record]));
    }

    public static function edit(): Action
    {
        return EditAction::make()
            ->label('تعديل البنود')
            ->visible(fn (Order $record): bool => $record->isEditable());
    }

    public static function recordDelivery(): Action
    {
        return Action::make('recordDelivery')
            ->label('تسليم جزئي')
            ->icon('heroicon-o-truck')
            ->color('info')
            ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Confirmed, OrderStatus::PartiallyDelivered], true))
            ->modalHeading(fn (Order $record): string => "تسليم جزئي للطلب {$record->order_number}")
            ->modalDescription('أدخل الكميات التي تم تسليمها الآن لكل بند. لا يمكن أن تتجاوز الكمية المتبقية، ويجب تسجيل وحدة واحدة على الأقل.')
            ->modalSubmitActionLabel('حفظ التسليم')
            ->form(fn (Order $record): array => self::deliveryForm($record))
            ->action(function (Order $record, array $data): void {
                $deliveries = self::normalizeDeliveries($data);

                if (! self::run($record, fn () => $record->recordDeliveries($deliveries))) {
                    return;
                }

                $title = $record->status === OrderStatus::Delivered
                    ? "تم تسليم الطلب {$record->order_number} بالكامل"
                    : "تم تسجيل تسليم جزئي للطلب {$record->order_number}";

                Notification::make()
                    ->title($title)
                    ->success()
                    ->send();
            });
    }

    public static function deliverAll(): Action
    {
        return Action::make('deliverAll')
            ->label('تأكيد التسليم')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Confirmed, OrderStatus::PartiallyDelivered], true))
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => "تأكيد التسليم للطلب {$record->order_number}")
            ->modalDescription('سيتم تسجيل تسليم كل الكميات المتبقية وتحويل حالة الطلب إلى «تم التسليم».')
            ->modalSubmitActionLabel('تأكيد التسليم')
            ->action(function (Order $record): void {
                if (! self::run($record, fn () => $record->deliverAllRemaining())) {
                    return;
                }

                Notification::make()
                    ->title("تم تسليم الطلب {$record->order_number} بالكامل")
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function deliveryForm(Order $record): array
    {
        $record->loadMissing('items');

        $components = [];

        foreach ($record->items as $item) {
            /** @var OrderItem $item */
            $label = $item->product_name.' — '.$item->product_code.' / '.$item->color
                .($item->size ? ' / '.$item->size : '');

            $components[] = TextInput::make("deliveries.{$item->id}")
                ->label($label)
                ->helperText("المطلوب: {$item->quantity} — تم تسليم: {$item->delivered_quantity} — المتبقي: {$item->remaining_quantity}")
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue((int) $item->remaining_quantity)
                ->default(0)
                ->live(onBlur: true)
                ->hint(fn (Get $get): string => 'المتبقي بعد هذا التسليم: '.max(0, (int) $item->remaining_quantity - (int) $get("deliveries.{$item->id}")));

            $components[] = Hidden::make("expected.{$item->id}")
                ->default((int) $item->delivered_quantity);
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{quantity: mixed, expected_delivered: mixed}>
     */
    private static function normalizeDeliveries(array $data): array
    {
        $deliveries = [];
        $rawQuantities = $data['deliveries'] ?? [];
        $rawExpected = $data['expected'] ?? [];

        foreach ($rawQuantities as $itemId => $quantity) {
            $deliveries[(int) $itemId] = [
                'quantity' => $quantity,
                'expected_delivered' => $rawExpected[$itemId] ?? null,
            ];
        }

        return $deliveries;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function persistNotes(Order $record, array $data): void
    {
        if (! array_key_exists('admin_notes', $data)) {
            return;
        }

        $record->update(['admin_notes' => $data['admin_notes']]);
    }

    /**
     * Runs a transition and refreshes the record so the page reflects the new
     * state. Domain failures become a danger notification; unexpected
     * exceptions keep their normal reporting behavior.
     */
    private static function run(Order $record, callable $transition): bool
    {
        try {
            $transition();

            $record->refresh();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('تعذّر تنفيذ الإجراء')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        return true;
    }
}
