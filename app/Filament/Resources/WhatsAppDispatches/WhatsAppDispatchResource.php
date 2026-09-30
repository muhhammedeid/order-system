<?php

namespace App\Filament\Resources\WhatsAppDispatches;

use App\Filament\Resources\WhatsAppDispatches\Pages\ListWhatsAppDispatches;
use App\Models\WhatsAppDispatch;
use App\Support\WhatsApp\Outbound\DispatchQueue;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WhatsAppDispatchResource extends Resource
{
    protected static ?string $model = WhatsAppDispatch::class;

    protected static ?string $slug = 'whatsapp-dispatches';

    protected static ?int $navigationSort = 32;

    public static function getNavigationLabel(): string
    {
        return __('dispatches.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dispatches.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.whatsapp');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['order', 'customer', 'template']))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('kind')->label(__('dispatches.kind'))->formatStateUsing(fn ($state) => __("dispatches.kinds.{$state}")),
                TextColumn::make('order.order_number')->label(__('dispatches.order'))->searchable(),
                TextColumn::make('customer.name')->label(__('dispatches.customer')),
                TextColumn::make('template.name')->label(__('dispatches.template')),
                TextColumn::make('status')->label(__('dispatches.status'))->badge()->formatStateUsing(fn ($state) => __("dispatches.statuses.{$state}")),
                TextColumn::make('attempts')->label(__('dispatches.attempts')),
                TextColumn::make('resolution_attempts')->label(__('dispatches.resolution_attempts')),
                TextColumn::make('failure_reason')->label(__('dispatches.reason'))->wrap(),
                TextColumn::make('sent_at')->label(__('dispatches.sent_at'))->dateTime()->sortable(),
                TextColumn::make('created_at')->label(__('dispatches.created_at'))->dateTime()->sortable(),
            ])->filters([
                SelectFilter::make('status')->label(__('dispatches.status'))->options(__('dispatches.statuses')),
                SelectFilter::make('kind')->label(__('dispatches.kind'))->options(__('dispatches.kinds')),
            ])->recordActions([
                Action::make('retry')->label(__('dispatches.retry'))->requiresConfirmation()
                    ->visible(fn (WhatsAppDispatch $record): bool => $record->kind !== 'campaign' && $record->status === 'failed'
                        && $record->attempts === 0 && $record->resolution_attempts < 3 && $record->provider_message_id === null)
                    ->action(fn (WhatsAppDispatch $record) => app(DispatchQueue::class)->retryFailed($record)),
            ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListWhatsAppDispatches::route('/')];
    }
}
