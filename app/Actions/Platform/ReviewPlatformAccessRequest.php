<?php

namespace App\Actions\Platform;

use App\Models\PlatformAccessGrant;
use App\Models\PlatformAccessRequest;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewPlatformAccessRequest
{
    public function execute(PlatformAccessRequest $request, User $reviewer, bool $approved, ?string $reviewNote = null): PlatformAccessRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $approved, $reviewNote): PlatformAccessRequest {
            /** @var PlatformAccessRequest $lockedRequest */
            $lockedRequest = PlatformAccessRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($lockedRequest->status === 'pending', 422, 'This platform access request has already been reviewed.');
            abort_unless($lockedRequest->role->isRequestable(), 422, 'This platform role cannot be granted through a request.');

            /** @var User $lockedReviewer */
            $lockedReviewer = User::query()->lockForUpdate()->findOrFail($reviewer->id);
            abort_unless($lockedReviewer->hasPlatformCapability(PlatformCapability::ManagePlatformAccess), 403);
            abort_if((int) $lockedRequest->user_id === (int) $lockedReviewer->id, 422, 'A user cannot review their own platform access request.');

            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($lockedRequest->user_id);

            if ($approved) {
                abort_unless($target->status === 'active' && $target->email_verified_at !== null, 422, 'Only active verified users can receive platform access.');

                $alreadyGranted = collect($lockedRequest->role->capabilities())
                    ->every(fn (PlatformCapability $capability): bool => $target->hasPlatformCapability($capability));

                if (! $alreadyGranted) {
                    $grant = new PlatformAccessGrant;
                    $grant->user()->associate($target);
                    $grant->role = $lockedRequest->role;
                    $grant->grantedBy()->associate($lockedReviewer);
                    $grant->granted_at = now();
                    $grant->reason = 'Approved platform access request #'.$lockedRequest->id.'.';
                    $grant->correlation_id = (string) Str::uuid();
                    $grant->save();
                }
            }

            $lockedRequest->status = $approved ? 'approved' : 'rejected';
            $lockedRequest->pending_key = null;
            $lockedRequest->reviewer()->associate($lockedReviewer);
            $lockedRequest->reviewed_at = now();
            $lockedRequest->review_note = $reviewNote;
            $lockedRequest->save();

            return $lockedRequest;
        }, attempts: 3);
    }
}
