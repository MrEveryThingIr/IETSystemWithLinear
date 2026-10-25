<?php

namespace App\Support\Market;

use App\Models\BusinessListing;
use App\Models\Group;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\LocalizedNumber;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NormalizeIntentDetails
{
    public const TYPES = ['text', 'long_text', 'number', 'quantity', 'date', 'time', 'date_range', 'place',
        'boolean', 'choice', 'multiple_choice', 'amount', 'link', 'reference'];

    /** @param list<array<string, mixed>> $details @return list<array<string, mixed>> */
    public function execute(User $user, array $details, bool $shapeOnly = false): array
    {
        Validator::make(['details' => $details], ['details' => ['array', 'max:50'], 'details.*' => ['required', 'array']])->validate();
        $result = [];
        $seen = [];
        foreach ($details as $position => $input) {
            $data = Validator::make($input, [
                'canonical_key' => ['nullable', Rule::in(array_keys(IntentDetailCatalog::all()))],
                'label' => ['nullable', 'string', 'max:120'], 'type' => ['required', Rule::in(self::TYPES)],
                'visibility' => ['required', 'in:same,private'], 'unit' => ['nullable', 'string', 'max:64'],
                'group_label' => ['nullable', 'string', 'max:120'], 'options' => ['sometimes', 'array', 'max:50'],
                'options.*' => ['string', 'max:120'],
            ])->validate();
            $key = $data['canonical_key'] ?? null;
            if ($key !== null && $key !== '') {
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['details' => __('composer.duplicate_detail')]);
                }
                $seen[$key] = true;
                $spec = IntentDetailCatalog::all()[$key];
                $data['type'] = $spec['type'];
                $data['label'] = __('composer.details.'.$key);
                $data['unit'] = $spec['unit'] ?? null;
                $data['options'] = $spec['options'] ?? [];
            } else {
                $data['canonical_key'] = null;
                $label = mb_strtolower(trim((string) ($data['label'] ?? '')));
                if ($label === '' || in_array($label, ['price', 'quantity', 'unit', 'schedule', 'location', 'قیمت', 'تعداد', 'مقدار', 'واحد', 'السعر', 'الكمية', 'الوحدة', '价格', '数量', '单位'], true)) {
                    throw ValidationException::withMessages(['details' => __('composer.core_detail')]);
                }
            }
            if ($shapeOnly) {
                $options = $data['options'] ?? [];
                unset($data['options']);
                $result[] = [...$data, 'payload' => ['options' => $options], 'position' => $position];

                continue;
            }
            $value = $input['value'] ?? null;
            $type = $data['type'];
            if (in_array($type, ['number', 'quantity'], true) && is_string($value)) {
                $value = LocalizedNumber::decimal($value);
            }
            if ($type === 'boolean' && in_array($value, ['0', '1'], true)) {
                $value = $value === '1';
            }
            $baseType = match ($type) {
                'text', 'place', 'link', 'time' => 'short_text', 'long_text' => 'long_text',
                'number', 'quantity' => 'number', 'date' => 'date', 'boolean' => 'boolean', 'choice' => 'select', default => null,
            };
            if ($baseType !== null) {
                $options = array_map(fn ($option): array => ['value' => $option, 'label' => $option], $data['options'] ?? []);
                $definition = SpaceContentSchema::normalizeDefinitionFields([['key' => 'value', 'label' => $data['label'], 'type' => $baseType,
                    'required' => true, 'options' => $type === 'choice' ? $options : []]]);
                $value = SpaceContentSchema::normalizePayload($definition, ['value' => $value])['value'];
            }
            if (in_array($type, ['number', 'quantity'], true)) {
                Validator::make(['value' => $value], ['value' => ['numeric', 'between:-999999999999999999,999999999999999999']])->validate();
            }
            if ($type === 'amount') {
                $value = MarketMoney::parse($value, 'details');
                $data['unit'] = 'IRT';
            }
            if ($type === 'link') {
                Validator::make(['link' => $value], ['link' => ['url:http,https', 'max:2048']])->validate();
            }
            if ($type === 'time') {
                Validator::make(['time' => $value], ['time' => ['date_format:H:i']])->validate();
            }
            if ($type === 'date_range') {
                $value = Validator::make(is_array($value) ? $value : [], ['start' => ['required', 'date_format:Y-m-d'],
                    'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start']])->validate();
            }
            if ($type === 'multiple_choice') {
                $value = Validator::make(['choices' => $value], ['choices' => ['required', 'array', 'max:50'],
                    'choices.*' => ['string', 'distinct', Rule::in($data['options'] ?? [])]])->validate()['choices'];
            }
            if ($type === 'reference') {
                $reference = Validator::make(is_array($value) ? $value : [], ['type' => ['required', 'in:content,group,catalog'], 'id' => ['required', 'integer', 'min:1']])->validate();
                $class = match ($reference['type']) {
                    'content' => SpaceContent::class, 'group' => Group::class, 'catalog' => BusinessListing::class,
                    default => throw ValidationException::withMessages(['details' => __('composer.invalid_reference')])
                };
                Gate::forUser($user)->authorize('view', $class::query()->findOrFail($reference['id']));
                $value = $reference;
            }
            if ($type === 'quantity' && trim((string) ($data['unit'] ?? '')) === '') {
                throw ValidationException::withMessages(['details' => __('composer.quantity_unit')]);
            }
            $options = $data['options'] ?? [];
            unset($data['options']);
            $result[] = [...$data, 'payload' => ['value' => $value, 'options' => $options], 'position' => $position];
        }

        return $result;
    }
}
