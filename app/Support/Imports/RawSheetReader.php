<?php

namespace App\Support\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class RawSheetReader implements ToArray
{
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }

    /**
     * Validates the header contract and returns data rows keyed
     * by the exact trimmed header names (no fuzzy matching).
     */
    public function keyedRows(array $requiredHeaders): array
    {
        if (empty($this->rows)) {
            return [];
        }

        $headerRow = $this->rows[0];
        $headers = collect($headerRow)
            ->map(fn ($cell) => trim((string) $cell))
            ->filter(fn ($cell) => filled($cell))
            ->values()
            ->all();

        foreach ($requiredHeaders as $required) {
            if (! in_array($required, $headers, true)) {
                throw new HeaderContractException(
                    'ملف الاستيراد يجب أن يحتوي على الأعمدة: '.implode('، ', $requiredHeaders)
                );
            }
        }

        $keyed = [];

        foreach (array_slice($this->rows, 1) as $index => $row) {
            $mapped = [];

            foreach ($headerRow as $cellIndex => $headerCell) {
                $header = trim((string) $headerCell);

                if (blank($header)) {
                    continue;
                }

                $mapped[$header] = isset($row[$cellIndex]) ? $row[$cellIndex] : null;
            }

            if (collect($mapped)->filter(fn ($cell) => filled(trim((string) $cell)))->isEmpty()) {
                continue;
            }

            $keyed[$index + 2] = $mapped;
        }

        return $keyed;
    }
}
