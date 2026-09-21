<?php

namespace App\Support\Profile;

final class ProfileFieldCatalog
{
    /** @return array<string, string> */
    public static function shareable(): array
    {
        return [
            'display_name' => 'ui.profile.display_name',
            'headline' => 'ui.profile.headline',
            'bio' => 'ui.profile.bio',
            'location_text' => 'ui.profile.location',
            'website_url' => 'ui.profile.website_url',
        ];
    }

    public static function supports(string $field): bool
    {
        return array_key_exists($field, self::shareable());
    }
}
