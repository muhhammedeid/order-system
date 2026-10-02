<?php

namespace App\Filament\Resources\WhatsAppCampaigns\RelationManagers;

use App\Models\WhatsAppCampaignRecipient;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('campaigns.audience');
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['customer', 'template', 'dispatch']))
            ->columns([
                TextColumn::make('customer.name')->label(__('campaigns.customer')),
                TextColumn::make('template.name')->label(__('campaigns.template')),
                TextColumn::make('status')->label(__('campaigns.status'))->getStateUsing(fn (WhatsAppCampaignRecipient $record): string => $record->dispatch?->status ?? $record->status)
                    ->formatStateUsing(fn (string $state): string => __("dispatches.statuses.{$state}"))->badge(),
                TextColumn::make('dispatch.attempts')->label(__('campaigns.attempts'))->default(0),
                TextColumn::make('dispatch.sent_at')->label(__('campaigns.sent_at'))->dateTime(),
                TextColumn::make('failure_reason')->label(__('campaigns.failure_reason'))->getStateUsing(fn (WhatsAppCampaignRecipient $record): ?string => $record->dispatch?->failure_reason ?? $record->failure_reason)->wrap(),
                TextColumn::make('dispatch.provider_message_id')->label(__('campaigns.provider_reference'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_url')->label(__('campaigns.product_url'))->toggleable(isToggledHiddenByDefault: true),
            ])->recordActions([
                Action::make('retry')->label(__('campaigns.retry'))->requiresConfirmation()
                    ->visible(fn (WhatsAppCampaignRecipient $record): bool => $record->dispatch?->status === 'failed' && $record->dispatch?->attempts === 0 && $record->dispatch?->resolution_attempts < 3 && $record->dispatch?->provider_message_id === null)
                    ->action(fn (WhatsAppCampaignRecipient $record) => $record->retryBeforeSend()),
            ]);
    }
}
