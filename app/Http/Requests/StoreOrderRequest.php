<?php

namespace App\Http\Requests;

use App\Rules\UsablePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', new UsablePhoneNumber],
            'company_name' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255', new UsablePhoneNumber],
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'customer_notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'الاسم مطلوب', 'phone.required' => 'رقم الموبايل مطلوب'];
    }
}
