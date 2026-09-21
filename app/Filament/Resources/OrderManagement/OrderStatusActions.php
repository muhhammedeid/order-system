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
            ->label(__('filament.orders.actions.confirm'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
            ->modalHeading(fn (Order $record): string => __('filament.orders.actions.confirm_heading', ['order' => $record->order_number]))
            ->modalDescription(__('filament.orders.actions.confirm_description'))
            ->modalSubmitActionLabel(__('filament.orders.actions.confirm'))
            ->form([
                Textarea::make('admin_notes')
                    ->label(__('filament.fields.admin_notes'))
                    ->helperText(__('filament.orders.actions.admin_notes_help'))
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
                    ->title(__('filament.orders.actions.confirmed', ['order' => $record->order_number]))
                    ->success()
                    ->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('filament.orders.actions.cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
            ->modalHeading(fn (Order $record): string => __('filament.orders.actions.cancel_heading', ['order' => $record->order_number]))
            ->modalDescription(__('filament.orders.actions.cancel_description'))
            ->modalSubmitActionLabel(__('filament.orders.actions.cancel_submit'))
            ->action(function (Order $record): void {
                if (! self::run($record, fn () => $record->cancel())) {
                    return;
                }

                Notification::make()
                    ->title(__('filament.orders.actions.cancelled', ['order' => $record->order_number]))
                    ->success()
                    ->send();
            });
    }

    public static function review(): Action
    {
        return Action::make('review')
            ->label(__('filament.orders.actions.review'))
            ->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
            ->url(fn (Order $record): string => OrderManagementResource::getUrl('confirm', ['record' => $record]));
    }

    public static function edit(): Action
    {
        return EditAction::make()
            ->label(__('filament.orders.actions.edit_items'))
            ->visible(fn (Order $record): bool => $record->isEditable());
    }

    public static function recordDelivery(): Action
    {
        return Action::make('recordDelivery')
            ->label(__('filament.orders.actions.partial_delivery'))
            ->icon('heroicon-o-truck')
            ->color('info')
            ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Confirmed, OrderStatus::PartiallyDelivered], true))
            ->modalHeading(fn (Order $record): string => __('filament.orders.actions.partial_heading', ['order' => $record->order_number]))
            ->modalDescription(__('filament.orders.actions.partial_description'))
            ->modalSubmitActionLabel(__('filament.orders.actions.save_delivery'))
            ->form(fn (Order $record): array => self::deliveryForm($record))
            ->action(function (Order $record, array $data): void {
                $deliveries = self::normalizeDeliveries($data);

                if (! self::run($record, fn () => $record->recordDeliveries($deliveries))) {
                    return;
                }

                $title = $record->status === OrderStatus::Delivered
                    ? __('filament.orders.actions.delivered_full', ['order' => $record->order_number])
                    : __('filament.orders.actions.delivered_partial', ['order' => $record->order_number]);

                Notification::make()
                    ->title($title)
                    ->success()
                    ->send();
            });
    }

    public static function deliverAll(): Action
    {
        return Action::make('deliverAll')
            ->label(__('filament.orders.actions.deliver_all'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Confirmed, OrderStatus::PartiallyDelivered], true))
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => __('filament.orders.actions.deliver_all_heading', ['order' => $record->order_number]))
            ->modalDescription(__('filament.orders.actions.deliver_all_description'))
            ->modalSubmitActionLabel(__('filament.orders.actions.deliver_all'))
            ->action(function (Order $record): void {
                if (! self::run($record, fn () => $record->deliverAllRemaining())) {
                    return;
                }

                Notification::make()
                    ->title(__('filament.orders.actions.delivered_full', ['order' => $record->order_number]))
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
            $dimensions = collect([$item->color, $item->size])
                ->filter(fn ($value) => filled($value))
                ->implode(' / ');

            $label = $item->product_name.' — '.$item->product_code
                .($dimensions === '' ? '' : ' / '.$dimensions);

            $components[] = TextInput::make("deliveries.{$item->id}")
                ->label($label)
                ->helperText(__('filament.orders.actions.delivery_per_color_summary', [
                    'required' => $item->requested_quantity,
                    'colors' => $item->effectiveColorCount(),
                    'delivered' => $item->delivered_quantity_per_color,
                    'deliverable' => $item->deliverable_quantity_per_color,
                ]))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue($item->deliverable_quantity_per_color)
                ->default(0)
                ->live(onBlur: true)
                ->hint(fn (Get $get): string => __('filament.orders.actions.remaining_after', [
                    'remaining' => max(0, $item->deliverable_quantity_per_color - (int) $get("deliveries.{$item->id}")),
                ]));

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
                ->title(__('filament.orders.actions.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        return true;
    }
}
