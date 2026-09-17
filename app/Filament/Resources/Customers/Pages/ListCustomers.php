<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Support\Imports\HeaderContractException;
use App\Support\Imports\ImportRunner;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(
                'importCustomers',
                'استيراد العملاء',
                fn (string $path) => ImportRunner::customers($path),
            ),
        ];
    }

    private function importAction(string $name, string $label, \Closure $import): Action
    {
        return Action::make($name)
            ->label($label)
            ->form([
                FileUpload::make('file')
                    ->label('ملف Excel')
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
                        ->title('فشل الاستيراد')
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
