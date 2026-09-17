<?php

namespace App\Filament\Resources\VariantSizes\Pages;

use App\Filament\Resources\VariantSizes\VariantSizeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVariantSize extends EditRecord
{
    protected static string $resource = VariantSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
