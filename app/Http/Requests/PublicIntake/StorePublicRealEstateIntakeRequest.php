<?php

namespace App\Http\Requests\PublicIntake;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicRealEstateIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $numericFields = [
            'phone',
            'land_area',
            'construction_area',
            'width',
            'length',
            'frontage_count',
            'built_year',
            'building_age_years',
            'bedrooms',
            'parking_spaces',
            'car_capacity',
            'motorbike_capacity',
            'asking_price',
            'deposit_amount',
            'monthly_rent_amount',
        ];

        $merge = [];

        foreach ($numericFields as $field) {
            if ($this->filled($field)) {
                $value = $this->normalizeDigits((string) $this->input($field));

                if (in_array($field, ['asking_price', 'deposit_amount', 'monthly_rent_amount'], true)) {
                    $value = str_replace([',', '٬', '،', ' '], '', $value);
                }

                $merge[$field] = $value;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'website' => ['nullable', 'max:0'],

            'intent' => ['required', Rule::in(['offer', 'need'])],
            'transaction_mode' => ['required', Rule::in(['sale', 'rent'])],

            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:7', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'exact_address' => ['nullable', 'string', 'max:2000'],
            'public_area' => ['nullable', 'string', 'max:180'],

            'property_class' => ['required', Rule::in([
                'residential',
                'commercial',
                'office',
                'land',
                'industrial',
                'agricultural',
                'mixed',
                'other',
            ])],
            'property_subtype' => ['nullable', 'string', 'max:80'],

            'land_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'construction_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'frontage_count' => ['nullable', 'integer', 'min:0', 'max:9'],

            'built_year' => ['nullable', 'integer', 'min:1200', 'max:2500'],
            'built_year_calendar' => ['nullable', Rule::in(['jalali', 'gregorian'])],
            'building_age_years' => ['nullable', 'integer', 'min:0', 'max:300'],
            'building_condition' => ['nullable', Rule::in([
                'new',
                'excellent',
                'good',
                'renovated',
                'needs_renovation',
                'old',
                'teardown',
            ])],

            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bedrooms_note' => ['nullable', 'string', 'max:255'],

            'cabinet_type' => ['nullable', 'string', 'max:40'],
            'has_false_ceiling' => ['nullable', 'boolean'],
            'false_ceiling_note' => ['nullable', 'string', 'max:255'],
            'heating_system' => ['nullable', 'string', 'max:60'],
            'cooling_system' => ['nullable', 'string', 'max:60'],
            'yard_finish' => ['nullable', 'string', 'max:60'],
            'flooring_type' => ['nullable', 'string', 'max:60'],
            'flooring_note' => ['nullable', 'string', 'max:255'],

            'has_parking' => ['nullable', 'boolean'],
            'parking_type' => ['nullable', 'string', 'max:40'],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:999'],
            'car_capacity' => ['nullable', 'integer', 'min:0', 'max:999'],
            'motorbike_capacity' => ['nullable', 'integer', 'min:0', 'max:999'],
            'parking_note' => ['nullable', 'string', 'max:255'],

            'roof_finish' => ['nullable', 'string', 'max:60'],
            'roof_note' => ['nullable', 'string', 'max:255'],
            'roof_has_parapet' => ['nullable', 'boolean'],

            'has_western_toilet' => ['nullable', 'boolean'],
            'has_iranian_toilet' => ['nullable', 'boolean'],

            'asking_price' => ['nullable', 'integer', 'min:0'],
            'deposit_amount' => ['nullable', 'integer', 'min:0'],
            'monthly_rent_amount' => ['nullable', 'integer', 'min:0'],
            'price_unit' => ['nullable', Rule::in(['toman', 'rial'])],

            'notes' => ['nullable', 'string', 'max:5000'],

            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'],

            'videos' => ['nullable', 'array', 'max:6'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],

            'audios' => ['nullable', 'array', 'max:6'],
            'audios.*' => ['file', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,video/webm', 'max:25600'],

            'recorded_audio' => ['nullable', 'file', 'mimetypes:audio/webm,audio/ogg,audio/mp4,video/webm', 'max:25600'],
            'recorded_video' => ['nullable', 'file', 'mimetypes:video/webm,video/mp4,video/quicktime', 'max:102400'],

        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => __('public_real_estate.validation.contact_name_required'),
            'phone.required' => __('public_real_estate.validation.phone_required'),
            'phone.regex' => __('public_real_estate.validation.phone_regex'),
            'intent.required' => __('public_real_estate.validation.intent_required'),
            'transaction_mode.required' => __('public_real_estate.validation.transaction_required'),
            'property_class.required' => __('public_real_estate.validation.property_class_required'),
            'built_year.min' => __('public_real_estate.validation.built_year_min'),
            'website.max' => __('public_real_estate.validation.invalid_request'),
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
