<?php

namespace App\Support\Imports;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportRunner
{
    public static function customers(string $relativePath): ImportResult
    {
        return (new CustomersImporter)->process(
            self::keyedRows($relativePath, CustomersImporter::HEADERS),
        );
    }

    public static function products(string $relativePath): ImportResult
    {
        return (new ProductsImporter)->process(
            self::keyedRows($relativePath, ProductsImporter::REQUIRED_HEADERS),
        );
    }

    private static function keyedRows(string $relativePath, array $requiredHeaders): array
    {
        $reader = new RawSheetReader;

        Excel::import($reader, Storage::disk('local')->path($relativePath));

        return $reader->keyedRows($requiredHeaders);
    }
}
