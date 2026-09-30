<?php

namespace App\Filament\Resources\OrderManagement;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemColorQuantity;
use App\Support\WhatsApp\Order\OrderStatusNotifier;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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
            ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Confirmed, OrderStatus::PartiallyDelivered], true)
                && $record->hasOutstandingColors())
            ->modalHeading(fn (Order $record): string => __('filament.orders.actions.partial_heading', ['order' => $record->order_number]))
            ->modalDescription(__('filament.orders.actions.partial_description'))
            ->modalSubmitActionLabel(__('filament.orders.actions.save_delivery'))
            ->form(fn (Order $record): array => self::deliveryForm($record))
            ->action(function (Order $record, array $data): void {
                $deliveries = self::normalizeDeliveries($data);

                if (! self::run($record, fn () => $record->recordDeliveries($deliveries), deliveryEvent: true)) {
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

    /**
     * One-time allocation of the historical unallocated delivered quantity
     * to the exact colors that received it. Available only while an item
     * still carries unallocated pieces.
     */
    public static function reconcileDeliveries(): Action
    {
        return Action::make('reconcileDeliveries')
            ->label(__('filament.orders.actions.reconcile_deliveries'))
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->visible(fn (Order $record): bool => $record->hasUnallocatedDeliveries())
            ->modalHeading(fn (Order $record): string => __('filament.orders.actions.reconcile_heading', ['order' => $record->order_number]))
            ->modalDescription(__('filament.orders.actions.reconcile_description'))
            ->modalSubmitActionLabel(__('filament.orders.actions.reconcile_submit'))
            ->form(fn (Order $record): array => self::reconciliationForm($record))
            ->action(function (Order $record, array $data): void {
                $allocations = self::normalizeReallocations($data);

                if ($allocations === []) {
                    Notification::make()
                        ->title(__('filament.orders.actions.failed'))
                        ->body(__('filament.orders.actions.reconcile_incomplete'))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                if (! self::run($record, fn () => $record->reconcileUnallocatedDeliveries($allocations))) {
                    return;
                }

                Notification::make()
                    ->title(__('filament.orders.actions.reconciled', ['order' => $record->order_number]))
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
     * One delivery row per ordered color that still requires delivery. The
     * rows come exclusively from this order's persisted snapshot color rows
     * (`order.items.colorQuantities`): fully delivered colors and fully
     * delivered items never appear, and the product's current colors are
     * never read.
     *
     * @return array<int, mixed>
     */
    private static function deliveryForm(Order $record): array
    {
        $record->loadMissing('items.outstandingColorQuantities');

        $components = [];

        foreach ($record->items as $item) {
            /** @var OrderItem $item */
            if ($item->hasUnallocatedDeliveries()) {
                $components[] = Placeholder::make("unallocated.{$item->id}")
                    ->label(self::itemColorLabel($item))
                    ->content(__('filament.orders.actions.unallocated_warning', [
                        'count' => $item->unallocated_delivered_quantity,
                    ]));

                continue;
            }

            foreach ($item->outstandingColorQuantities as $colorRow) {
                /** @var OrderItemColorQuantity $colorRow */
                $components[] = TextInput::make("deliveries.{$colorRow->id}")
                    ->label(self::itemColorLabel($item, $colorRow->color))
                    ->helperText(__('filament.orders.actions.delivery_color_summary', [
                        'required' => $colorRow->requested_quantity,
                        'delivered' => $colorRow->delivered_quantity,
                        'remaining' => $colorRow->remaining_quantity,
                    ]))
                    ->placeholder(__('filament.orders.actions.deliver_now'))
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue($colorRow->remaining_quantity)
                    ->default(0)
                    ->live(onBlur: true)
                    ->hint(fn (Get $get): string => __('filament.orders.actions.remaining_after', [
                        'color' => $colorRow->color,
                        'remaining' => max(0, $colorRow->remaining_quantity - (int) $get("deliveries.{$colorRow->id}")),
                    ]));

                $components[] = Hidden::make("expected.{$colorRow->id}")
                    ->default((int) $colorRow->delivered_quantity);
            }
        }

        return $components;
    }

    /**
     * Explicit product, code and color label shared by the delivery rows.
     */
    private static function itemColorLabel(OrderItem $item, ?string $color = null): string
    {
        $label = $item->product_name.' — '.$item->product_code;

        if (filled($color)) {
            $label .= ' — '.$color;
        }

        return $label;
    }

    /**
     * Per-color allocation inputs for every item that still carries an
     * unallocated historical delivered quantity.
     *
     * @return array<int, mixed>
     */
    private static function reconciliationForm(Order $record): array
    {
        $record->loadMissing('items.colorQuantities');

        $components = [];

        foreach ($record->items as $item) {
            /** @var OrderItem $item */
            if (! $item->hasUnallocatedDeliveries()) {
                continue;
            }

            $sizes = filled($item->size) ? ' / '.$item->size : '';

            $components[] = Placeholder::make("unallocated_total.{$item->id}")
                ->label($item->product_name.' — '.$item->product_code.$sizes)
                ->content(__('filament.orders.actions.unallocated_warning', [
                    'count' => $item->unallocated_delivered_quantity,
                ]));

            foreach ($item->colorQuantities as $colorRow) {
                /** @var OrderItemColorQuantity $colorRow */
                $components[] = TextInput::make("allocations.{$colorRow->id}")
                    ->label($item->product_name.' / '.$colorRow->color)
                    ->helperText(__('filament.orders.actions.reconcile_color_summary', [
                        'required' => $colorRow->requested_quantity,
                        'delivered' => $colorRow->delivered_quantity,
                        'remaining' => $colorRow->remaining_quantity,
                    ]))
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue($colorRow->remaining_quantity)
                    ->default(0);
            }
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

        foreach ($rawQuantities as $colorRowId => $quantity) {
            $deliveries[(int) $colorRowId] = [
                'quantity' => $quantity,
                'expected_delivered' => $rawExpected[$colorRowId] ?? null,
            ];
        }

        return $deliveries;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed> allocations keyed by color row id
     */
    private static function normalizeReallocations(array $data): array
    {
        $allocations = [];
        $raw = $data['allocations'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        foreach ($raw as $colorRowId => $amount) {
            $allocations[(int) $colorRowId] = ($amount === null || $amount === '') ? 0 : $amount;
        }

        return $allocations;
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
     * exceptions are reported and surfaced safely instead of breaking the
     * admin page.
     *
     * After a successful transition the automatic WhatsApp update runs in its
     * own failure boundary: a WhatsApp outage never reports the completed
     * transition as failed.
     */
    private static function run(Order $record, callable $transition, bool $deliveryEvent = false): bool
    {
        $from = $record->status;

        try {
            DB::transaction(function () use ($transition, $record, $from, $deliveryEvent): void {
                $transition();
                $record->refresh();
                self::notifyStatusChange($record, $from, $deliveryEvent);
            });
        } catch (RuntimeException|ValidationException $exception) {
            Notification::make()
                ->title(__('filament.orders.actions.failed'))
                ->body(self::failureBody($exception))
                ->danger()
                ->persistent()
                ->send();

            return false;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('filament.orders.actions.failed'))
                ->body(__('filament.orders.actions.failed_body'))
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        return true;
    }

    /**
     * Automatic operational WhatsApp update for the committed status change.
     * Skipped sends (no number, inactive template, disabled integration) stay
     * silent; only real send failures warn the operator.
     */
    private static function notifyStatusChange(Order $record, OrderStatus $from, bool $deliveryEvent): void
    {
        try {
            $result = app(OrderStatusNotifier::class)->statusChanged($record, $from, $deliveryEvent);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp order status notification failed', [
                'order_id' => $record->id,
                'exception' => $exception::class,
            ]);

            $result = null;
        }

        if ($result !== null && ! $result->hasFailures()) {
            return;
        }

        Notification::make()
            ->title(__('admin.whatsapp.notifications.status_update_failed'))
            ->body(__('admin.whatsapp.notifications.status_update_failed_body'))
            ->warning()
            ->persistent()
            ->send();
    }

    private static function failureBody(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return (string) (collect($exception->errors())->flatten()->first()
                ?? __('filament.orders.actions.failed_body'));
        }

        return $exception->getMessage() !== ''
            ? $exception->getMessage()
            : __('filament.orders.actions.failed_body');
    }
}
