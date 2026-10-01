<?php

namespace App\Support;

final class BusinessDirectory
{
    public const KINDS = [
        'real_estate' => 'دفتر املاک',
        'retail' => 'فروشگاه',
        'services' => 'دفتر خدمات',
        'construction' => 'ساختمان و پیمانکاری',
        'workshop' => 'کارگاه / تولید',
        'professional_office' => 'دفتر حرفه‌ای',
        'organization' => 'سازمان / مجموعه',
        'other' => 'سایر',
    ];

    public const ROLES = [
        'owner' => 'مالک',
        'manager' => 'مدیر',
        'member' => 'عضو',
    ];

    public const VISIBILITIES = [
        'private' => 'خصوصی',
        'members' => 'اعضای مجموعه',
        'public' => 'عمومی',
    ];
}
