<?php

namespace App\Actions\Iet;

use App\Models\IetRateVersion;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetRateMath;
use Illuminate\Support\Facades\DB;

final class PublishIetRateAdjustment
{
    public function __construct(
        private readonly EnsureIetEconomy $economy,
        private readonly IetRateMath $math,
    ) {}

    /**
     * @param array<string, int|float|string|bool|null> $criteria
     */
    public function execute(
        User $user,
        int $adjustmentPpm,
        string $reason,
        array $criteria = [],
    ): IetRateVersion {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManagePlatformAccess), 403);

        $reason = trim($reason);
        abort_if($reason === '' || mb_strlen($reason) > 500, 422, 'A concise IET rate rationale is required.');

        $this->economy->execute();

        return DB::transaction(function () use ($user, $adjustmentPpm, $reason, $criteria): IetRateVersion {
            $current = IetRateVersion::query()
                ->orderByDesc('effective_at')
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->firstOrFail();

            $ratio = $this->math->adjustedRatio($current, $adjustmentPpm);

            return IetRateVersion::query()->create([
                'sequence' => (int) $current->sequence + 1,
                'usd_numerator' => $ratio['numerator'],
                'usd_denominator' => $ratio['denominator'],
                'adjustment_ppm' => $adjustmentPpm,
                'reason' => $reason,
                'criteria' => $criteria,
                'effective_at' => now(),
                'created_by_user_id' => $user->id,
            ]);
        }, attempts: 3);
    }
}
