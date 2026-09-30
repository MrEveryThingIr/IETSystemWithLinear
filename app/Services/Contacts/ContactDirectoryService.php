<?php

namespace App\Services\Contacts;

use App\Models\ActorAddress;
use App\Models\ContactPoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactDirectoryService
{
    public const CONTACT_KINDS = [
        'email',
        'mobile',
        'phone',
        'website',
        'whatsapp',
        'telegram',
        'other',
    ];

    public const VISIBILITIES = [
        'private',
        'contacts',
        'members',
        'public',
    ];

    public const ADDRESS_TYPES = [
        'residence',
        'work',
        'branch',
        'billing',
        'shipping',
        'project_site',
        'other',
    ];

    public function createContactPoint(Model $owner, array $data): ContactPoint
    {
        $normalized = $this->normalize((string) $data['kind'], (string) $data['value']);
        $this->assertValid((string) $data['kind'], (string) $data['value'], $normalized);

        return DB::transaction(function () use ($owner, $data, $normalized): ContactPoint {
            $query = ContactPoint::query()
                ->where('contactable_type', $owner->getMorphClass())
                ->where('contactable_id', $owner->getKey())
                ->where('kind', $data['kind']);

            $isFirst = ! $query->exists();
            $isPrimary = (bool) ($data['is_primary'] ?? false) || $isFirst;

            if ($isPrimary) {
                $query->update(['is_primary' => false]);
            }

            $point = new ContactPoint([
                'kind' => $data['kind'],
                'label' => $this->nullableTrim($data['label'] ?? null),
                'value' => trim((string) $data['value']),
                'normalized_value' => $normalized,
                'is_primary' => $isPrimary,
                'is_verified' => false,
                'visibility' => $data['visibility'] ?? 'private',
                'notes' => $this->nullableTrim($data['notes'] ?? null),
            ]);

            $point->contactable()->associate($owner);
            $point->save();

            return $point->refresh();
        });
    }

    public function updateContactPoint(Model $owner, ContactPoint $point, array $data): ContactPoint
    {
        $this->guardOwnedContact($owner, $point);

        $kind = (string) ($data['kind'] ?? $point->kind);
        $value = (string) ($data['value'] ?? $point->value);
        $normalized = $this->normalize($kind, $value);
        $this->assertValid($kind, $value, $normalized);

        return DB::transaction(function () use ($owner, $point, $data, $kind, $value, $normalized): ContactPoint {
            $isPrimary = (bool) ($data['is_primary'] ?? $point->is_primary);

            if ($isPrimary) {
                ContactPoint::query()
                    ->where('contactable_type', $owner->getMorphClass())
                    ->where('contactable_id', $owner->getKey())
                    ->where('kind', $kind)
                    ->whereKeyNot($point->getKey())
                    ->update(['is_primary' => false]);
            }

            $changedIdentity = $point->kind !== $kind || $point->normalized_value !== $normalized;

            $point->fill([
                'kind' => $kind,
                'label' => $this->nullableTrim($data['label'] ?? $point->label),
                'value' => trim($value),
                'normalized_value' => $normalized,
                'is_primary' => $isPrimary,
                'visibility' => $data['visibility'] ?? $point->visibility,
                'notes' => array_key_exists('notes', $data)
                    ? $this->nullableTrim($data['notes'])
                    : $point->notes,
            ]);

            // A changed email/phone/etc. is no longer verified automatically.
            if ($changedIdentity) {
                $point->is_verified = false;
                $point->verified_at = null;
            }

            $point->save();

            return $point->refresh();
        });
    }

    public function deleteContactPoint(Model $owner, ContactPoint $point): void
    {
        $this->guardOwnedContact($owner, $point);

        DB::transaction(function () use ($owner, $point): void {
            $kind = $point->kind;
            $wasPrimary = $point->is_primary;
            $point->delete();

            if ($wasPrimary) {
                $replacement = ContactPoint::query()
                    ->where('contactable_type', $owner->getMorphClass())
                    ->where('contactable_id', $owner->getKey())
                    ->where('kind', $kind)
                    ->oldest()
                    ->first();

                $replacement?->update(['is_primary' => true]);
            }
        });
    }

    public function createAddress(Model $owner, array $data): ActorAddress
    {
        return DB::transaction(function () use ($owner, $data): ActorAddress {
            $sameType = ActorAddress::query()
                ->where('addressable_type', $owner->getMorphClass())
                ->where('addressable_id', $owner->getKey())
                ->where('type', $data['type']);

            $isPrimary = (bool) ($data['is_primary'] ?? false) || ! $sameType->exists();

            if ($isPrimary) {
                $sameType->update(['is_primary' => false]);
            }

            $address = new ActorAddress([
                ...$this->cleanAddressData($data),
                'is_primary' => $isPrimary,
            ]);

            $address->addressable()->associate($owner);
            $address->save();

            return $address->refresh();
        });
    }

    public function updateAddress(Model $owner, ActorAddress $address, array $data): ActorAddress
    {
        $this->guardOwnedAddress($owner, $address);

        return DB::transaction(function () use ($owner, $address, $data): ActorAddress {
            $type = $data['type'] ?? $address->type;
            $isPrimary = (bool) ($data['is_primary'] ?? $address->is_primary);

            if ($isPrimary) {
                ActorAddress::query()
                    ->where('addressable_type', $owner->getMorphClass())
                    ->where('addressable_id', $owner->getKey())
                    ->where('type', $type)
                    ->whereKeyNot($address->getKey())
                    ->update(['is_primary' => false]);
            }

            $address->fill([
                ...$this->cleanAddressData([...$address->toArray(), ...$data]),
                'is_primary' => $isPrimary,
            ])->save();

            return $address->refresh();
        });
    }

    public function deleteAddress(Model $owner, ActorAddress $address): void
    {
        $this->guardOwnedAddress($owner, $address);

        DB::transaction(function () use ($owner, $address): void {
            $type = $address->type;
            $wasPrimary = $address->is_primary;
            $address->delete();

            if ($wasPrimary) {
                ActorAddress::query()
                    ->where('addressable_type', $owner->getMorphClass())
                    ->where('addressable_id', $owner->getKey())
                    ->where('type', $type)
                    ->oldest()
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });
    }

    public function normalize(string $kind, string $value): string
    {
        $value = $this->normalizeDigits(trim($value));

        return match ($kind) {
            'email' => mb_strtolower($value),
            'mobile', 'phone', 'whatsapp' => $this->normalizePhone($value),
            'website' => mb_strtolower(rtrim($value, '/')),
            'telegram' => mb_strtolower(ltrim($value, '@')),
            default => mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value),
        };
    }

    private function assertValid(string $kind, string $value, string $normalized): void
    {
        if (! in_array($kind, self::CONTACT_KINDS, true)) {
            throw ValidationException::withMessages(['kind' => 'نوع راه ارتباطی معتبر نیست.']);
        }

        if ($normalized === '') {
            throw ValidationException::withMessages(['value' => 'مقدار راه ارتباطی خالی است.']);
        }

        if ($kind === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['value' => 'ایمیل معتبر نیست.']);
        }

        if ($kind === 'website' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                'value' => 'آدرس وب باید کامل باشد؛ مثلاً https://example.com',
            ]);
        }

        if (in_array($kind, ['mobile', 'phone', 'whatsapp'], true) && strlen($normalized) < 7) {
            throw ValidationException::withMessages(['value' => 'شماره تماس معتبر نیست.']);
        }
    }

    private function normalizePhone(string $value): string
    {
        $value = preg_replace('/[^\d+]/u', '', $value) ?? $value;

        if (str_starts_with($value, '0098')) {
            $value = '+98'.substr($value, 4);
        }

        return $value;
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

    private function cleanAddressData(array $data): array
    {
        return [
            'type' => $data['type'] ?? 'other',
            'label' => $this->nullableTrim($data['label'] ?? null),
            'country_code' => strtoupper(trim((string) ($data['country_code'] ?? 'IR'))),
            'province' => $this->nullableTrim($data['province'] ?? null),
            'city' => $this->nullableTrim($data['city'] ?? null),
            'district' => $this->nullableTrim($data['district'] ?? null),
            'street' => $this->nullableTrim($data['street'] ?? null),
            'alley' => $this->nullableTrim($data['alley'] ?? null),
            'building_no' => $this->nullableTrim($data['building_no'] ?? null),
            'unit' => $this->nullableTrim($data['unit'] ?? null),
            'postal_code' => $this->nullableTrim($data['postal_code'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'notes' => $this->nullableTrim($data['notes'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function guardOwnedContact(Model $owner, ContactPoint $point): void
    {
        abort_unless(
            $point->contactable_type === $owner->getMorphClass()
            && (string) $point->contactable_id === (string) $owner->getKey(),
            404
        );
    }

    private function guardOwnedAddress(Model $owner, ActorAddress $address): void
    {
        abort_unless(
            $address->addressable_type === $owner->getMorphClass()
            && (string) $address->addressable_id === (string) $owner->getKey(),
            404
        );
    }
}
