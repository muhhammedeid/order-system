<?php

namespace App\Filament\Resources\WhatsAppConversations;

use App\Filament\Resources\WhatsAppConversations\Pages\ListWhatsAppConversations;
use App\Filament\Resources\WhatsAppConversations\Pages\ViewWhatsAppConversation;
use App\Filament\Resources\WhatsAppConversations\Tables\WhatsAppConversationsTable;
use App\Models\WhatsAppConversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only operational Inbox: one record per WhatsApp conversation.
 * Conversations are created by inbound processing, never from the panel.
 */
class WhatsAppConversationResource extends Resource
{
    protected static ?string $model = WhatsAppConversation::class;

    protected static ?string $slug = 'whatsapp-conversations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'provider_chat_id';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.whatsapp_inbox');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.whatsapp');
    }

    public static function getModelLabel(): string
    {
        return __('admin.whatsapp.inbox.conversation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.whatsapp_inbox');
    }

    public static function table(Table $table): Table
    {
        return WhatsAppConversationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('customer:id,name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppConversations::route('/'),
            'view' => ViewWhatsAppConversation::route('/{record}'),
        ];
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

    public static function getNavigationBadge(): ?string
    {
        $unread = WhatsAppConversation::query()->where('unread_count', '>', 0)->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
}
