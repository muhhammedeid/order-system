<?php

namespace App\Filament\Concerns;

use App\Support\Imports\HeaderContractException;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

trait HasImportAction
{
    protected function importAction(string $name, string $label, \Closure $import): Action
    {
        return Action::make($name)
            ->label($label)
            ->form([
                FileUpload::make('file')
                    ->label(__('filament.common.excel_file'))
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required(),
            ])
            ->action(function (array $data) use ($label, $import) {
                try {
                    $result = $import($data['file']);
                } catch (HeaderContractException $exception) {
                    Notification::make()
                        ->title(__('filament.common.import_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt();
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                Notification::make()
                    ->title($label)
                    ->body(implode("\n", $result->summaryLines()))
                    ->status($result->invalid() ? 'warning' : 'success')
                    ->persistent()
                    ->send();
            });
    }
}
