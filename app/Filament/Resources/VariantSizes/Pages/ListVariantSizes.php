<?php

namespace App\Filament\Resources\VariantSizes\Pages;

use App\Filament\Resources\VariantSizes\VariantSizeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVariantSizes extends ListRecords
{
    protected static string $resource = VariantSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
