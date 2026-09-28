<?php

namespace App\Actions\Iet;

use App\IetExchangeStatus;
use App\Models\IetExchangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelIetExchangeRequest
{
    public function execute(IetExchangeRequest $request, User $user): IetExchangeRequest
    {
        abort_unless((int) $request->user_id === (int) $user->id, 403);

        return DB::transaction(function () use ($request): IetExchangeRequest {
            $locked = IetExchangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($locked->status === IetExchangeStatus::Pending, 422);
            $locked->cancel();

            return $locked->fresh();
        }, attempts: 3);
    }
}
