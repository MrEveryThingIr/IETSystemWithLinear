<?php

return [
    'session_key' => 'locale',

    'locales' => [
        'en' => [
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'intl_locale' => 'en',
            'default_calendar' => 'gregory',
            'first_day_of_week' => 7,
        ],
        'ar' => [
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'direction' => 'rtl',
            'intl_locale' => 'ar',
            'default_calendar' => 'gregory',
            'first_day_of_week' => 6,
        ],
        'zh_CN' => [
            'name' => 'Chinese',
            'native_name' => '简体中文',
            'direction' => 'ltr',
            'intl_locale' => 'zh-CN',
            'default_calendar' => 'gregory',
            'first_day_of_week' => 1,
        ],
        'fa' => [
            'name' => 'Persian',
            'native_name' => 'فارسی',
            'direction' => 'rtl',
            'intl_locale' => 'fa-IR',
            'default_calendar' => 'persian',
            'first_day_of_week' => 6,
        ],
    ],
];
