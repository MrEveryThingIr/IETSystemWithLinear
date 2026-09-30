<?php

namespace App\Services\Contacts;

use App\Models\BusinessContact;
use App\Models\ContactPoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BusinessContactResolver
{
    public function __construct(
        private readonly ContactDirectoryService $directory,
    ) {
    }

    public function resolve(
        Model $owner,
        string $displayName,
        string $phone,
        string $source = 'manual'
    ): BusinessContact {
        $normalized = $this->directory->normalize('mobile', $phone);

        $existing = BusinessContact::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->whereHas('contactPoints', function ($query) use ($normalized): void {
                $query->whereIn('kind', ['mobile', 'phone'])
                    ->where('normalized_value', $normalized);
            })
            ->first();

        if ($existing) {
            if (
                trim($displayName) !== ''
                && ($existing->display_name === '' || $existing->display_name === 'بدون نام')
            ) {
                $existing->update(['display_name' => trim($displayName)]);
            }

            return $existing;
        }

        return DB::transaction(function () use ($owner, $displayName, $phone, $source): BusinessContact {
            $contact = new BusinessContact([
                'display_name' => trim($displayName) !== '' ? trim($displayName) : 'بدون نام',
                'status' => 'active',
                'source' => $source,
            ]);

            $contact->owner()->associate($owner);
            $contact->save();

            $this->directory->createContactPoint($contact, [
                'kind' => 'mobile',
                'label' => 'شماره معرفی‌شده',
                'value' => $phone,
                'visibility' => 'private',
                'is_primary' => true,
            ]);

            return $contact->refresh();
        });
    }
}
