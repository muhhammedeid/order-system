<?php

namespace App\Filament\Concerns;

use App\Support\Exports\BusinessExport;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Shared wiring for synchronous admin Excel exports (R04): resolves the
 * table's current filtered query (or selected records) and streams the
 * generated XLSX back to the authenticated admin.
 */
trait HasExcelExport
{
    protected function excelExportAction(string $name, string $label, Closure $makeExport): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function () use ($makeExport) {
                /** @var BusinessExport $export */
                $export = $makeExport($this->getTableQueryForExport());

                return $export->download();
            });
    }

    protected static function excelExportSelectedAction(string $name, string $label, Closure $makeExport): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (EloquentCollection $records) use ($makeExport) {
                /** @var BusinessExport $export */
                $export = $makeExport($records);

                return $export->download();
            });
    }
}
