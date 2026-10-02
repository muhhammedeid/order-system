<?php

namespace App\Filament\Resources\WhatsAppConversations\Tables;

use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use App\Models\WhatsAppConversation;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_title')
                    ->label(__('admin.whatsapp.inbox.contact'))
                    ->state(fn (WhatsAppConversation $record): string => $record->displayTitle())
                    ->description(fn (WhatsAppConversation $record): ?string => $record->customer_id !== null
                        ? $record->customer?->phone
                        : $record->provider_chat_id)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(function (Builder $query) use ($search): void {
                            $query->where('provider_chat_id', 'like', "%{$search}%")
                                ->orWhere('resolved_phone', 'like', "%{$search}%")
                                ->orWhereHas('customer', fn (Builder $customer) => $customer
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('customer_code', 'like', "%{$search}%"));
                        }))
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('last_message_preview')
                    ->label(__('admin.whatsapp.inbox.last_message'))
                    ->state(fn (WhatsAppConversation $record): string => self::preview($record))
                    ->color('gray')
                    ->wrap(),
                TextColumn::make('last_message_at')
                    ->label(__('admin.whatsapp.inbox.last_activity'))
                    ->since()
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('unread_count')
                    ->label(__('admin.whatsapp.inbox.unread'))
                    ->badge()
                    ->color('danger')
                    ->weight('bold')
                    ->state(fn (WhatsAppConversation $record): ?string => $record->unread_count > 0
                        ? (string) $record->unread_count
                        : null),
                TextColumn::make('customer_id')
                    ->label(__('admin.whatsapp.inbox.linked'))
                    ->badge()
                    ->state(fn (WhatsAppConversation $record): string => $record->customer_id !== null
                        ? __('admin.whatsapp.inbox.linked_yes')
                        : __('admin.whatsapp.inbox.linked_no'))
                    ->color(fn (WhatsAppConversation $record): string => $record->customer_id !== null ? 'success' : 'gray')
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin.whatsapp.inbox.open')),
            ])
            ->recordUrl(fn (WhatsAppConversation $record): string => WhatsAppConversationResource::getUrl('view', ['record' => $record]))
            ->defaultSort('last_message_at', 'desc')
            ->emptyStateHeading(__('admin.whatsapp.inbox.empty'));
    }

    private static function preview(WhatsAppConversation $record): string
    {
        $prefix = $record->last_message_direction === 'outbound'
            ? __('admin.whatsapp.inbox.direction_out').' '
            : '';

        if (filled($record->last_message_preview)) {
            return $prefix.$record->last_message_preview;
        }

        return $prefix.__('admin.whatsapp.inbox.media_placeholder');
    }
}
