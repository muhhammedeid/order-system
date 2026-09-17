<?php

namespace App\Filament\Resources\VariantColors\Pages;

use App\Filament\Resources\VariantColors\VariantColorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVariantColors extends ListRecords
{
    protected static string $resource = VariantColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
