<?php

namespace App\Filament\Resources\WhatsAppCampaigns;

use App\Filament\Resources\WhatsAppCampaigns\Pages\CreateWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\EditWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ListWhatsAppCampaigns;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ViewWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\RelationManagers\RecipientsRelationManager;
use App\Models\Customer;
use App\Models\Product;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WhatsAppCampaignResource extends Resource
{
    protected static ?string $model = WhatsAppCampaign::class;

    protected static ?string $slug = 'whatsapp-campaigns';

    protected static ?int $navigationSort = 31;

    public static function getNavigationLabel(): string
    {
        return __('campaigns.models');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.whatsapp');
    }

    public static function getModelLabel(): string
    {
        return __('campaigns.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('campaigns.models');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // Layout: draft identity/product/date first; audience and variations on create only.
        return $schema->components([
            TextInput::make('name')->label(__('campaigns.name'))->required()->maxLength(120),
            Select::make('product_id')->label(__('campaigns.product'))->required()->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Product::query()->where('active', true)->where('name', 'like', "%{$search}%")->limit(30)->pluck('name', 'id')->all())
                ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name),
            DateTimePicker::make('scheduled_at')->label(__('campaigns.scheduled_at')),
            Select::make('customer_ids')->label(__('campaigns.audience'))->multiple()->required()->searchable()->visibleOn('create')
                ->getSearchResultsUsing(fn (string $search): array => Customer::query()->where('whatsapp_marketing_status', 'subscribed')->where('name', 'like', "%{$search}%")->limit(30)->get()->filter->hasUsableWhatsAppNumber()->pluck('name', 'id')->all())
                ->getOptionLabelsUsing(fn (array $values): array => Customer::whereKey($values)->pluck('name', 'id')->all()),
            Select::make('template_ids')->label(__('campaigns.variations'))->multiple()->required()->searchable()->visibleOn('create')
                ->options(fn (): array => WhatsAppTemplate::query()->where('active', true)->where('type', 'product_announcement')->pluck('name', 'id')->all()),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(__('campaigns.name')),
            TextEntry::make('product.name')->label(__('campaigns.product')),
            TextEntry::make('status')->label(__('campaigns.status'))->formatStateUsing(fn (string $state): string => __("campaigns.statuses.{$state}")),
            TextEntry::make('scheduled_at')->label(__('campaigns.scheduled_at'))->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('product')->withCount('recipients'))
            ->columns([
                TextColumn::make('name')->label(__('campaigns.name'))->searchable(),
                TextColumn::make('product.name')->label(__('campaigns.product')),
                TextColumn::make('status')->label(__('campaigns.status'))->badge()->formatStateUsing(fn (string $state): string => __("campaigns.statuses.{$state}")),
                TextColumn::make('recipients_count')->label(__('campaigns.audience')),
                TextColumn::make('scheduled_at')->label(__('campaigns.scheduled_at'))->dateTime(),
            ])->recordActions([
                ViewAction::make(), EditAction::make()->visible(fn (WhatsAppCampaign $record): bool => $record->status === 'draft'),
                Action::make('start')->label(__('campaigns.start'))->requiresConfirmation()->visible(fn (WhatsAppCampaign $record): bool => $record->status === 'draft')->action(fn (WhatsAppCampaign $record) => $record->start()),
                Action::make('pause')->label(__('campaigns.pause'))->requiresConfirmation()->visible(fn (WhatsAppCampaign $record): bool => $record->status === 'running')->action(fn (WhatsAppCampaign $record) => $record->pause()),
                Action::make('resume')->label(__('campaigns.resume'))->requiresConfirmation()->visible(fn (WhatsAppCampaign $record): bool => $record->status === 'paused')->action(fn (WhatsAppCampaign $record) => $record->resume()),
            ])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [RecipientsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppCampaigns::route('/'),
            'create' => CreateWhatsAppCampaign::route('/create'),
            'view' => ViewWhatsAppCampaign::route('/{record}'),
            'edit' => EditWhatsAppCampaign::route('/{record}/edit'),
        ];
    }
}
