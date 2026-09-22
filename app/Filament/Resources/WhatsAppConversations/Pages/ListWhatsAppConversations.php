<?php

namespace App\Filament\Resources\WhatsAppConversations\Pages;

use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppConversations extends ListRecords
{
    protected static string $resource = WhatsAppConversationResource::class;
}
