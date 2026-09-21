<?php

namespace App\Actions\Profile;

use App\ProfileIntentScheduleKind;
use App\ProfileItemVisibility;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NormalizeProfileIntentData
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(User $user, array $input): array
    {
        $input['timezone'] ??= $user->timezone ?: config('app.timezone');
        $input['recurrence_interval'] ??= 1;
        $input['round_trip'] ??= false;
        $input['visibility'] ??= ProfileItemVisibility::Inherited->value;

        $validator = Validator::make($input, [
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999999999'],
            'unit' => ['nullable', 'string', 'max:64'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'origin_text' => ['nullable', 'string', 'max:255'],
            'destination_text' => ['nullable', 'string', 'max:255'],
            'round_trip' => ['required', 'boolean'],
            'return_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'schedule_kind' => ['required', Rule::enum(ProfileIntentScheduleKind::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'timezone' => ['required', 'timezone'],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:365'],
            'recurrence_weekdays' => ['nullable', 'array', 'max:7'],
            'recurrence_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrence_day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'time_window_start' => ['nullable', 'date_format:H:i'],
            'time_window_end' => ['nullable', 'date_format:H:i'],
            'visibility' => ['required', Rule::enum(ProfileItemVisibility::class)],
        ]);

        $validator->after(function ($validator) use ($input): void {
            $schedule = ProfileIntentScheduleKind::tryFrom((string) ($input['schedule_kind'] ?? ''));

            if ($schedule === ProfileIntentScheduleKind::Weekly && empty($input['recurrence_weekdays'])) {
                $validator->errors()->add('recurrence_weekdays', 'Choose at least one weekday for a weekly declaration.');
            }

            if ($schedule === ProfileIntentScheduleKind::Monthly && empty($input['recurrence_day_of_month'])) {
                $validator->errors()->add('recurrence_day_of_month', 'Choose a day of month for a monthly declaration.');
            }

            $origin = trim((string) ($input['origin_text'] ?? ''));
            $destination = trim((string) ($input['destination_text'] ?? ''));

            if (($origin === '') xor ($destination === '')) {
                $validator->errors()->add('origin_text', 'Origin and destination must be supplied together.');
            }

            if ((bool) ($input['round_trip'] ?? false)) {
                if ($origin === '' || $destination === '') {
                    $validator->errors()->add('round_trip', 'Round trips require both origin and destination.');
                }

                if (! array_key_exists('return_after_days', $input) || $input['return_after_days'] === null || $input['return_after_days'] === '') {
                    $validator->errors()->add('return_after_days', 'Round trips require a return offset in days.');
                }
            }

            if (($input['quantity'] ?? null) !== null && trim((string) ($input['unit'] ?? '')) === '') {
                $validator->errors()->add('unit', 'A unit is required when quantity is specified.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $schedule = ProfileIntentScheduleKind::from((string) $data['schedule_kind']);

        foreach (['title', 'description', 'unit', 'location_text', 'origin_text', 'destination_text'] as $key) {
            $value = isset($data[$key]) ? Str::of((string) $data[$key])->squish()->toString() : '';
            $data[$key] = $value !== '' ? $value : null;
        }

        $data['quantity'] = isset($data['quantity']) ? (float) $data['quantity'] : null;
        $data['round_trip'] = (bool) $data['round_trip'];
        $data['recurrence_interval'] = (int) $data['recurrence_interval'];
        $data['recurrence_weekdays'] = array_values(array_unique(array_map(
            'intval',
            $data['recurrence_weekdays'] ?? [],
        )));
        sort($data['recurrence_weekdays']);

        if ($schedule !== ProfileIntentScheduleKind::Weekly) {
            $data['recurrence_weekdays'] = null;
        }

        if ($schedule !== ProfileIntentScheduleKind::Monthly) {
            $data['recurrence_day_of_month'] = null;
        }

        if (! $schedule->isRecurring()) {
            $data['recurrence_interval'] = 1;
            $data['recurrence_weekdays'] = null;
            $data['recurrence_day_of_month'] = null;
        }

        if (! $data['round_trip']) {
            $data['return_after_days'] = null;
        }

        return $data;
    }
}
