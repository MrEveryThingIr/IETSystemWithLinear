<?php

namespace App\Actions\Exchange;

use App\Models\Actor;
use App\Models\IetValuationSnapshot;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetValueMath;
use InvalidArgumentException;

class PublishIetValuation
{
    /**
     * @param  array<string, int|float|string|bool|null>  $factors
     */
    public function execute(
        User $user,
        string $xPercent,
        ?string $note = null,
        array $factors = [],
        string $source = 'manual',
    ): IetValuationSnapshot {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $actor = User::query()->with('actor')->find($user->id)?->actor;
        abort_unless($actor instanceof Actor, 403);

        try {
            $picos = IetValueMath::picosFromXPercent($xPercent);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 5000, 422, 'Valuation note is too long.');
        abort_unless(in_array($source, ['manual', 'policy'], true), 422);

        $factors['x_percent'] = IetValueMath::xPercent($picos);

        return IetValuationSnapshot::query()->create([
            'usd_pico_per_iet' => $picos,
            'source' => $source,
            'factors' => $factors !== [] ? $factors : null,
            'note' => $note !== '' ? $note : null,
            'effective_at' => now(),
            'created_by_actor_id' => $actor->id,
        ]);
    }
}
