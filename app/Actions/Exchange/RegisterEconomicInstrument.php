<?php

namespace App\Actions\Exchange;

use App\EconomicInstrumentKind;
use App\Models\EconomicInstrument;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Str;

class RegisterEconomicInstrument
{
    public function execute(
        User $user,
        string $code,
        string $name,
        EconomicInstrumentKind $kind,
    ): EconomicInstrument {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $code = strtoupper(trim($code));
        $name = Str::squish($name);

        abort_unless(
            preg_match('/^[A-Z0-9][A-Z0-9._-]{1,47}$/', $code) === 1,
            422,
            'Instrument code must contain 2-48 uppercase letters, digits, dot, underscore or dash.',
        );
        abort_if($name === '' || mb_strlen($name) > 180, 422, 'Instrument name is required.');

        $existing = EconomicInstrument::query()->where('code', $code)->first();

        if ($existing instanceof EconomicInstrument) {
            abort_unless($existing->kind === $kind, 422, 'Instrument code already exists with another kind.');

            return $existing;
        }

        return EconomicInstrument::query()->create([
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'settlement_enabled' => false,
            'active' => true,
            'metadata' => [
                'registered_manually' => true,
                'registered_by_user_id' => $user->id,
            ],
        ]);
    }
}
