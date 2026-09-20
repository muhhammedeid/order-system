<?php

namespace App\Support\Exports;

use App\Models\Customer;

/**
 * Customer grain export using the same headers as the customer import
 * contract, preserving official accounting Customer Codes.
 */
class CustomersExport extends BusinessExport
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Customer Code',
            'Customer Name',
            'Company Name',
            'Phone',
            'WhatsApp',
            'Governorate',
            'City',
            'Address',
        ];
    }

    /**
     * @param  Customer  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        return [
            $record->customer_code,
            $record->name,
            $record->company_name,
            $record->phone,
            $record->whatsapp,
            $record->governorate,
            $record->city,
            $record->address,
        ];
    }

    public function title(): string
    {
        return 'Customers';
    }

    protected function fileName(): string
    {
        return $this->timestampedFileName('customers');
    }
}
