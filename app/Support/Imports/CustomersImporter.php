<?php

namespace App\Support\Imports;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomersImporter
{
    public const HEADERS = [
        'Customer Code',
        'Customer Name',
        'Company Name',
        'Phone',
        'WhatsApp',
        'Governorate',
        'City',
        'Address',
    ];

    public function process(array $keyedRows): ImportResult
    {
        $result = new ImportResult;
        $seenCodes = [];

        foreach ($keyedRows as $rowNumber => $row) {
            try {
                $status = DB::transaction(function () use ($row, &$seenCodes) {
                    return $this->processRow($row, $seenCodes);
                });

                $result->addRow($rowNumber, $status['status'], $status['reason'] ?? null);
            } catch (\Throwable $exception) {
                $result->addRow($rowNumber, 'invalid', $this->reason($exception));
            }
        }

        return $result;
    }

    private function processRow(array $row, array &$seenCodes): array
    {
        $name = $this->requireTrimmed($row, 'Customer Name');
        $phone = $this->requireTrimmed($row, 'Phone');
        $code = trim((string) ($row['Customer Code'] ?? ''));

        $provided = $this->providedFields($row);

        if (blank($code)) {
            if (Customer::query()->where('phone', $phone)->exists()) {
                return ['status' => 'invalid', 'reason' => "رقم الموبايل {$phone} مستخدم بالفعل وبدون كود عميل"];
            }

            Customer::query()->create($provided + [
                'customer_code' => null,
                'phone' => $phone,
            ]);

            return ['status' => 'created'];
        }

        if (in_array($code, $seenCodes, true)) {
            return ['status' => 'invalid', 'reason' => "كود عميل مكرر داخل الملف: {$code}"];
        }

        $seenCodes[] = $code;

        $existing = Customer::query()->where('customer_code', $code)->first();

        if ($existing) {
            $existing->fill($provided);
            $existing->save();

            return ['status' => 'updated'];
        }

        Customer::query()->create($provided + [
            'customer_code' => $code,
            'phone' => $phone,
        ]);

        return ['status' => 'created'];
    }

    private function providedFields(array $row): array
    {
        return collect([
            'name' => 'Customer Name',
            'company_name' => 'Company Name',
            'whatsapp' => 'WhatsApp',
            'governorate' => 'Governorate',
            'city' => 'City',
            'address' => 'Address',
        ])
            ->filter(fn ($header) => filled(trim((string) ($row[$header] ?? ''))))
            ->mapWithKeys(fn ($header, $field) => [$field => trim((string) $row[$header])])
            ->all();
    }

    private function requireTrimmed(array $row, string $header): string
    {
        $value = trim((string) ($row[$header] ?? ''));

        if (blank($value)) {
            throw new \RuntimeException("الحقل {$header} مطلوب");
        }

        return $value;
    }

    private function reason(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if ($message === '' || str_contains($message, 'SQLSTATE')) {
            $message = 'بيانات غير صالحة في الصف';
        }

        return $message;
    }
}
