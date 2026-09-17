<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Validator;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'name',
        'company_name',
        'phone',
        'whatsapp',
        'governorate',
        'city',
        'address',
        'notes',
    ];

    protected function customerCode(): Attribute
    {
        return Attribute::set(function ($value) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        });
    }

    public static function validate(array $data): array
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) ($data['name'] ?? ''));
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = trim((string) ($data['phone'] ?? ''));
        }

        Validator::make(
            $data,
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:255'],
                'customer_code' => ['nullable', 'string', 'max:255'],
            ],
        )->validate();

        return $data;
    }
}
