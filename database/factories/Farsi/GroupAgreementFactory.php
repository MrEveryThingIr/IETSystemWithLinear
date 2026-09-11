<?php

namespace Database\Factories\Farsi;

use App\Models\GroupAgreement;
use Database\Factories\GroupAgreementFactory as BaseGroupAgreementFactory;

class GroupAgreementFactory extends BaseGroupAgreementFactory
{
    protected $model = GroupAgreement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => GroupFactory::new(),
            'name' => fake('fa_IR')->randomElement(['شرایط مشارکت در پروژه', 'توافق‌نامه ایمنی کارگاه', 'پروتکل گزارش‌دهی پیشرفت']),
            'required_for_admission' => true,
        ];
    }
}
