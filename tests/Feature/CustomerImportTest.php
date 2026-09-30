<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\Imports\CustomersImporter;
use App\Support\Imports\HeaderContractException;
use App\Support\Imports\ImportResult;
use App\Support\Imports\RawSheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    private const ROW = [
        'Customer Code' => '',
        'Customer Name' => '',
        'Company Name' => '',
        'Phone' => '',
        'WhatsApp' => '',
        'Governorate' => '',
        'City' => '',
        'Address' => '',
    ];

    private function import(array $rows): ImportResult
    {
        $keyed = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;
            $keyed[$rowNumber] = array_merge(self::ROW, $row);
        }

        return (new CustomersImporter)->process($keyed);
    }

    public function test_missing_required_header_rejects_the_whole_file(): void
    {
        $reader = new RawSheetReader;
        $reader->rows = [
            ['Customer Name', 'Phone', 'Company Name'],
            ['Test', '0100', 'Co'],
        ];

        $this->expectException(HeaderContractException::class);
        $this->expectExceptionMessage('Customer Code');

        $reader->keyedRows(CustomersImporter::HEADERS);
    }

    public function test_creates_customer_with_code(): void
    {
        $result = $this->import([[
            'Customer Code' => 'C100',
            'Customer Name' => 'Imported Store',
            'Phone' => '01001234567',
            'City' => 'Nasr City',
        ]]);

        $this->assertSame(1, $result->created());
        $this->assertSame(0, $result->invalid());

        $this->assertDatabaseHas('customers', [
            'customer_code' => 'C100',
            'name' => 'Imported Store',
            'phone' => '01001234567',
            'city' => 'Nasr City',
        ]);
    }

    public function test_creates_customer_without_code(): void
    {
        $result = $this->import([[
            'Customer Name' => 'No Code Store',
            'Phone' => '01001234567',
        ]]);

        $this->assertSame(1, $result->created());
        $this->assertDatabaseHas('customers', [
            'customer_code' => null,
            'name' => 'No Code Store',
        ]);
    }

    public function test_updates_existing_customer_by_code(): void
    {
        Customer::factory()->create(['customer_code' => 'C100', 'name' => 'Old Name']);

        $result = $this->import([[
            'Customer Code' => 'C100',
            'Customer Name' => 'Updated Name',
            'Phone' => '01111111111',
        ]]);

        $this->assertSame(1, $result->updated());
        $this->assertDatabaseHas('customers', [
            'customer_code' => 'C100',
            'name' => 'Updated Name',
        ]);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_blank_code_with_matching_phone_is_rejected(): void
    {
        Customer::factory()->create(['phone' => '01001234567']);

        $result = $this->import([[
            'Customer Name' => 'Duplicate Phone Store',
            'Phone' => '01001234567',
        ]]);

        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('موبايل', $result->invalidDescriptions()[0]);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_duplicate_code_within_file_is_rejected(): void
    {
        $result = $this->import([
            ['Customer Code' => 'C100', 'Customer Name' => 'First', 'Phone' => '01001111111'],
            ['Customer Code' => 'C100', 'Customer Name' => 'Second', 'Phone' => '01002222222'],
        ]);

        $this->assertSame(1, $result->created());
        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('مكرر', $result->invalidDescriptions()[0]);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_invalid_rows_do_not_block_valid_rows(): void
    {
        $result = $this->import([
            ['Customer Name' => '', 'Phone' => '01001111111'],
            ['Customer Name' => 'Valid Store', 'Phone' => '01002222222'],
        ]);

        $this->assertSame(1, $result->created());
        $this->assertSame(1, $result->invalid());
        $this->assertStringContainsString('صف 2', $result->invalidDescriptions()[0]);
        $this->assertDatabaseHas('customers', ['name' => 'Valid Store']);
    }

    public function test_failed_row_rolls_back_completely(): void
    {
        $result = $this->import([
            [
                'Customer Code' => 'C100',
                'Customer Name' => 'Valid So Far',
                'Phone' => '', // missing phone fails after other work
            ],
        ]);

        $this->assertSame(1, $result->invalid());
        $this->assertDatabaseMissing('customers', ['customer_code' => 'C100']);
        $this->assertDatabaseCount('customers', 0);
    }
}
