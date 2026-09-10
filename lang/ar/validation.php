<?php

return [
    'accepted' => 'يجب قبول حقل :attribute.',
    'after' => 'يجب أن يكون حقل :attribute تاريخًا بعد :date.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'boolean' => 'يجب أن تكون قيمة حقل :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد حقل :attribute غير متطابق.',
    'date' => 'يجب أن يكون حقل :attribute تاريخًا صالحًا.',
    'email' => 'يجب أن يكون حقل :attribute بريدًا إلكترونيًا صالحًا.',
    'exists' => 'قيمة :attribute المحددة غير صالحة.',
    'in' => 'قيمة :attribute المحددة غير صالحة.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عناصر.',
        'file' => 'يجب ألا يتجاوز حجم ملف :attribute مقدار :max كيلوبايت.',
        'numeric' => 'يجب ألا تزيد قيمة :attribute على :max.',
        'string' => 'يجب ألا يزيد طول :attribute على :max حرفًا.',
    ],
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عناصر على الأقل.',
        'file' => 'يجب ألا يقل حجم ملف :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min أحرف.',
    ],
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون حقل :attribute نصًا.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة :attribute في تسريب بيانات. يرجى اختيار قيمة مختلفة.',
    ],
    'custom' => [],
    'attributes' => [
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'username' => 'اسم المستخدم',
        'locale' => 'اللغة',
    ],
];
