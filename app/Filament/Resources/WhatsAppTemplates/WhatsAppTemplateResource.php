<?php

namespace App\Filament\Resources\WhatsAppTemplates;

use App\Filament\Resources\WhatsAppTemplates\Pages\CreateWhatsAppTemplate;
use App\Filament\Resources\WhatsAppTemplates\Pages\EditWhatsAppTemplate;
use App\Filament\Resources\WhatsAppTemplates\Pages\ListWhatsAppTemplates;
use App\Filament\Resources\WhatsAppTemplates\Schemas\WhatsAppTemplateForm;
use App\Filament\Resources\WhatsAppTemplates\Tables\WhatsAppTemplatesTable;
use App\Models\WhatsAppTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Reusable marketing/general message templates. No send action exists in this
 * resource; operational order messages stay code/localization-driven.
 */
class WhatsAppTemplateResource extends Resource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static ?string $slug = 'whatsapp-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.whatsapp_templates');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.whatsapp');
    }

    public static function getModelLabel(): string
    {
        return __('admin.whatsapp.templates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.whatsapp.templates.models');
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsAppTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppTemplates::route('/'),
            'create' => CreateWhatsAppTemplate::route('/create'),
            'edit' => EditWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}
