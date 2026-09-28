<?php

namespace App\Actions\Iet;

use App\IetExchangeDirection;
use App\IetExchangeStatus;
use App\Models\IetExchangeRequest;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetWalletBalance;
use Illuminate\Support\Facades\DB;

final class ReviewIetExchangeRequest
{
    public function __construct(
        private readonly IetWalletBalance $balances,
        private readonly PostIetExchangeJournal $post,
    ) {}

    public function confirm(
        IetExchangeRequest $request,
        User $reviewer,
        ?string $reference = null,
    ): IetExchangeRequest {
        abort_unless($reviewer->hasPlatformCapability(PlatformCapability::ManagePlatformAccess), 403);

        return DB::transaction(function () use ($request, $reviewer, $reference): IetExchangeRequest {
            $locked = IetExchangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($locked->status === IetExchangeStatus::Pending, 422);

            if ($locked->direction === IetExchangeDirection::Cashout) {
                $owner = User::query()->findOrFail($locked->user_id);
                $this->balances->assertAtLeast($owner, (int) $locked->iet_amount_minor);
            }

            $entry = $this->post->execute($locked, $reviewer);

            $reference = trim((string) $reference);
            if ($reference !== '') {
                $locked->forceFill(['external_reference' => mb_substr($reference, 0, 255)]);
            }

            $locked->confirm($reviewer, $entry);

            return $locked->fresh(['user', 'rateVersion', 'fiatUnit', 'reviewer', 'journalEntry']);
        }, attempts: 3);
    }

    public function reject(
        IetExchangeRequest $request,
        User $reviewer,
        string $note,
    ): IetExchangeRequest {
        abort_unless($reviewer->hasPlatformCapability(PlatformCapability::ManagePlatformAccess), 403);

        $note = trim($note);
        abort_if($note === '' || mb_strlen($note) > 2000, 422, 'A rejection reason is required.');

        return DB::transaction(function () use ($request, $reviewer, $note): IetExchangeRequest {
            $locked = IetExchangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($locked->status === IetExchangeStatus::Pending, 422);
            $locked->reject($reviewer, $note);

            return $locked->fresh(['user', 'rateVersion', 'fiatUnit', 'reviewer']);
        }, attempts: 3);
    }
}
