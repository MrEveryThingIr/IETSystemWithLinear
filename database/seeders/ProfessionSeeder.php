<?php

namespace Database\Seeders;

use App\Models\Profession;
use Illuminate\Database\Seeder;

class ProfessionSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            [
                'code' => 'construction',
                'name' => 'Construction & Building',
                'name_fa' => 'ساختمان و عمران',
                'children' => [
                    ['code' => 'mason', 'name' => 'Mason', 'name_fa' => 'بنا'],
                    ['code' => 'construction_worker', 'name' => 'Construction Worker', 'name_fa' => 'کارگر ساختمانی'],
                    ['code' => 'electrician', 'name' => 'Electrician', 'name_fa' => 'برق‌کار'],
                    ['code' => 'plumber', 'name' => 'Plumber', 'name_fa' => 'لوله‌کش'],
                    ['code' => 'carpenter', 'name' => 'Carpenter', 'name_fa' => 'نجار'],
                    ['code' => 'painter', 'name' => 'Building Painter', 'name_fa' => 'نقاش ساختمان'],
                    ['code' => 'hvac_technician', 'name' => 'HVAC Technician', 'name_fa' => 'تأسیسات و تهویه'],
                    ['code' => 'welder', 'name' => 'Welder', 'name_fa' => 'جوشکار'],
                    ['code' => 'tiler', 'name' => 'Tiler', 'name_fa' => 'کاشی‌کار'],
                ],
            ],
            [
                'code' => 'real_estate',
                'name' => 'Real Estate',
                'name_fa' => 'املاک',
                'children' => [
                    ['code' => 'real_estate_agent', 'name' => 'Real Estate Agent', 'name_fa' => 'مشاور املاک'],
                    ['code' => 'property_appraiser', 'name' => 'Property Appraiser', 'name_fa' => 'ارزیاب ملک'],
                ],
            ],
            [
                'code' => 'retail',
                'name' => 'Retail',
                'name_fa' => 'فروش و خرده‌فروشی',
                'children' => [
                    ['code' => 'salesperson', 'name' => 'Salesperson', 'name_fa' => 'فروشنده'],
                    ['code' => 'store_manager', 'name' => 'Store Manager', 'name_fa' => 'مدیر فروشگاه'],
                ],
            ],
            [
                'code' => 'professional_services',
                'name' => 'Professional Services',
                'name_fa' => 'خدمات حرفه‌ای',
                'children' => [
                    ['code' => 'accountant', 'name' => 'Accountant', 'name_fa' => 'حسابدار'],
                    ['code' => 'driver', 'name' => 'Driver', 'name_fa' => 'راننده'],
                    ['code' => 'cleaner', 'name' => 'Cleaner', 'name_fa' => 'نظافت‌کار'],
                    ['code' => 'technician', 'name' => 'Technician', 'name_fa' => 'تکنسین'],
                ],
            ],
        ];

        foreach ($tree as $order => $rootData) {
            $children = $rootData['children'];
            unset($rootData['children']);

            $root = Profession::query()->updateOrCreate(
                ['code' => $rootData['code']],
                [
                    ...$rootData,
                    'parent_id' => null,
                    'is_active' => true,
                    'sort_order' => $order * 100,
                ]
            );

            foreach ($children as $childOrder => $child) {
                Profession::query()->updateOrCreate(
                    ['code' => $child['code']],
                    [
                        ...$child,
                        'parent_id' => $root->getKey(),
                        'is_active' => true,
                        'sort_order' => ($order * 100) + $childOrder + 1,
                    ]
                );
            }
        }
    }
}
