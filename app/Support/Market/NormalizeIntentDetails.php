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

    /** Library-only types that custom fields cannot pick. */
    private const LIBRARY_TYPES = ['year'];

    /**
     * @param  list<array<string, mixed>>  $details
     * @param  bool  $need  a Need may state a range or set; an Offer states a fact
     * @return list<array<string, mixed>>
     */
    public function execute(User $user, array $details, bool $shapeOnly = false, bool $need = false): array
    {
        Validator::make(['details' => $details], ['details' => ['array', 'max:50'], 'details.*' => ['required', 'array']])->validate();
        $result = [];
        $seen = [];
        foreach ($details as $position => $input) {
            $data = Validator::make($input, [
                'canonical_key' => ['nullable', Rule::in(array_keys(IntentDetailCatalog::all()))],
                'label' => ['nullable', 'string', 'max:120'], 'type' => ['required', Rule::in([...self::TYPES, ...self::LIBRARY_TYPES])],
                'visibility' => ['required', 'in:same,private'], 'unit' => ['nullable', 'string', 'max:64'],
                'group_label' => ['nullable', 'string', 'max:120'], 'options' => ['sometimes', 'array', 'max:50'], 'note' => ['nullable', 'string', 'max:280'],
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
                $data['label'] = IntentDetailCatalog::label($key);
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
            $op = ! empty($key) && $need ? (string) ($input['op'] ?? 'exact') : 'exact';
            $constraint = null;
            $calendar = null;
            if ($type === 'year') {
                $first = collect([$input['min'] ?? null, $input['max'] ?? null, $value])->first(fn ($candidate): bool => ! DetailValue::blank($candidate));
                $calendar = $first === null ? null : YearSpan::guessCalendar((int) LocalizedNumber::decimal((string) $first));
            }
            if ($op !== 'exact') {
                $constraint = DetailValue::constraint($type, $op, $input, $data['options'] ?? [], $calendar);
                $value = null;
                $options = $data['options'] ?? [];
                unset($data['options']);
                $payload = ['value' => null, 'constraint' => $constraint, 'options' => $options];
                $payload = $this->decorate($payload, $input, $calendar);
                $result[] = [...$data, 'payload' => $payload, 'position' => $position];

                continue;
            }
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
            if ($type === 'year') {
                $value = DetailValue::scalar('year', $value, $calendar);
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
            $result[] = [...$data, 'payload' => $this->decorate(['value' => $value, 'options' => $options], $input, $calendar), 'position' => $position];
        }

        return $result;
    }

    /**
     * Adds the optional note and the year's explicit calendar to a payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function decorate(array $payload, array $input, ?string $calendar): array
    {
        $note = trim((string) ($input['note'] ?? ''));
        if ($note !== '') {
            $payload['note'] = $note;
        }
        if ($calendar !== null) {
            $payload['calendar'] = $calendar;
        }

        return $payload;
    }
}
