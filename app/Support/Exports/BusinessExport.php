<?php

namespace App\Support\Exports;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shared behaviour for synchronous admin Excel exports (R04):
 * chunked query reads, formula-injection-safe value binding, and a
 * predictable timestamped download name.
 */
abstract class BusinessExport implements FromQuery, WithCustomChunkSize, WithCustomValueBinder, WithHeadings, WithMapping, WithTitle
{
    public const BUSINESS_TIMEZONE = 'Africa/Cairo';

    public function __construct(protected Builder $query) {}

    /**
     * @return array<int, string>
     */
    abstract public function headings(): array;

    /**
     * @return array<int, mixed>
     */
    abstract public function map($record): array;

    abstract public function title(): string;

    abstract protected function fileName(): string;

    public function query(): Builder
    {
        return $this->query;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Strings are always written as text cells: this neutralizes
     * spreadsheet formula injection and preserves leading zeros,
     * phone numbers, and large identifier values. Numbers stay numeric.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit(StringHelper::sanitizeUTF8($value), DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    public function download(): BinaryFileResponse
    {
        return Excel::download($this, $this->fileName());
    }

    protected function localDateTime(?CarbonInterface $value): ?string
    {
        return $value?->timezone(self::BUSINESS_TIMEZONE)->format('Y-m-d H:i');
    }

    protected function timestampedFileName(string $prefix): string
    {
        return $prefix.'-'.now(self::BUSINESS_TIMEZONE)->format('Y-m-d-His').'.xlsx';
    }
}
