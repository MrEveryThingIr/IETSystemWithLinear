<?php

namespace App\Support\Docs;

use App\Models\DocInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Who may read the documentation: everyone, or (invite-only mode) members and holders of an invitation link. */
class DocsAccess
{
    private const SESSION_KEY = 'docs.invited';

    public function inviteOnly(): bool
    {
        return config('docs.access') === 'invite';
    }

    public function allows(Request $request): bool
    {
        return ! $this->inviteOnly()
            || $request->user() !== null
            || $request->session()->get(self::SESSION_KEY) === true;
    }

    public function redeem(Request $request, string $token): bool
    {
        $granted = DB::transaction(function () use ($token): bool {
            $invitation = DocInvitation::query()->where('token_hash', DocInvitation::hashToken($token))->lockForUpdate()->first();

            if (! $invitation instanceof DocInvitation || ! $invitation->isUsable()) {
                return false;
            }

            $invitation->increment('uses_count');

            return true;
        });

        if ($granted) {
            $request->session()->put(self::SESSION_KEY, true);
        }

        return $granted;
    }
}
