<?php

namespace App\Filament\Resources\VariantColors\Pages;

use App\Filament\Resources\VariantColors\VariantColorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVariantColor extends EditRecord
{
    protected static string $resource = VariantColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
