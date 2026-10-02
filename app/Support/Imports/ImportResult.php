<?php

namespace App\Support\Imports;

class ImportResult
{
    /** @var array<int, array{row: int, status: string, reason: ?string}> */
    public array $rows = [];

    public function created(): int
    {
        return $this->count('created');
    }

    public function updated(): int
    {
        return $this->count('updated');
    }

    public function invalid(): int
    {
        return $this->count('invalid');
    }

    public function addRow(int $row, string $status, ?string $reason = null): void
    {
        $this->rows[] = ['row' => $row, 'status' => $status, 'reason' => $reason];
    }

    public function invalidDescriptions(): array
    {
        return collect($this->rows)
            ->filter(fn ($row) => $row['status'] === 'invalid')
            ->map(fn ($row) => "صف {$row['row']}: {$row['reason']}")
            ->values()
            ->all();
    }

    public function summaryLines(): array
    {
        $lines = [
            "أُنشئ: {$this->created()}",
            "محدّث: {$this->updated()}",
            "غير صالح: {$this->invalid()}",
        ];

        $invalid = $this->invalidDescriptions();

        if ($invalid) {
            $lines[] = '';
            $lines = array_merge($lines, array_slice($invalid, 0, 15));

            if (count($invalid) > 15) {
                $lines[] = '... و'.(count($invalid) - 15).' صفوف أخرى';
            }
        }

        return $lines;
    }

    private function count(string $status): int
    {
        return collect($this->rows)->where('status', $status)->count();
    }
}
