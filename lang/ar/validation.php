<?php

/*
|--------------------------------------------------------------------------
| Arabic validation messages
|--------------------------------------------------------------------------
|
| Partial override: only the rules used by this project are translated.
| Any missing key falls back to the framework English message.
|
*/

return [

    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'date' => 'يجب أن يكون :attribute تاريخًا صحيحًا.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صحيحًا.',
    'url' => 'يجب أن يكون :attribute رابطًا صحيحًا.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'exists' => 'القيمة المحددة في :attribute غير موجودة.',
    'in' => 'القيمة المحددة في :attribute غير صحيحة.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'mimes' => 'يجب أن يكون :attribute من نوع: :values.',
    'dimensions' => 'أبعاد الصورة في :attribute غير مناسبة.',
    'min' => [
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'string' => 'يجب ألا تقل حروف :attribute عن :min حرفًا.',
        'array' => 'يجب ألا تقل عناصر :attribute عن :min عنصرًا.',
    ],
    'max' => [
        'numeric' => 'يجب ألا تزيد قيمة :attribute عن :max.',
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
        'string' => 'يجب ألا تزيد حروف :attribute عن :max حرفًا.',
        'array' => 'يجب ألا تزيد عناصر :attribute عن :max عنصرًا.',
    ],
    'between' => [
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string' => 'يجب أن يكون عدد حروف :attribute بين :min و :max.',
    ],
    'gt' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
    ],
    'gte' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من أو تساوي :value.',
    ],
    'lt' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
    ],
    'lte' => [
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من أو تساوي :value.',
    ],

    'attributes' => [
        'name' => 'الاسم',
        'phone' => 'رقم الموبايل',
        'whatsapp' => 'رقم واتساب',
        'company_name' => 'اسم الشركة',
        'governorate' => 'المحافظة',
        'city' => 'المدينة',
        'address' => 'العنوان',
        'customer_notes' => 'ملاحظات الطلب',
        'admin_notes' => 'ملاحظات الإدارة',
        'customer_code' => 'كود العميل',
        'product_code' => 'كود المنتج',
        'product_name' => 'اسم المنتج',
        'category' => 'الفئة',
        'category_id' => 'الفئة',
        'price' => 'السعر',
        'price_visibility' => 'ظهور السعر',
        'active' => 'الحالة',
        'slug' => 'الرابط',
        'description' => 'الوصف',
        'quantity' => 'الكمية',
        'available_quantity' => 'الكمية المتاحة',
        'color' => 'اللون',
        'size' => 'المقاس',
        'status' => 'الحالة',
        'whatsapp_number' => 'رقم واتساب',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'image' => 'الصورة',
        'images' => 'الصور',
        'file' => 'الملف',
    ],

];
