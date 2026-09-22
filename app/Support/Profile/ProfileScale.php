<?php

namespace App\Support\Profile;

final class ProfileScale
{
    public static function percentFromWeight(float|string|null $weight): ?int
    {
        if ($weight === null) {
            return null;
        }

        return max(0, min(100, (int) round((float) $weight * 100)));
    }

    public static function skillLevelKey(int $percent): string
    {
        return match (true) {
            $percent <= 20 => 'beginner',
            $percent <= 40 => 'basic',
            $percent <= 60 => 'intermediate',
            $percent <= 80 => 'advanced',
            default => 'expert',
        };
    }

    public static function importanceLevelKey(int $percent): string
    {
        return match (true) {
            $percent <= 20 => 'low',
            $percent <= 40 => 'moderate',
            $percent <= 60 => 'important',
            $percent <= 80 => 'high',
            default => 'critical',
        };
    }
}
