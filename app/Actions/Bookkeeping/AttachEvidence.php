<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Assets\CreateContextAsset;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a receipt, bank slip or SMS screenshot to an entry. Files are kept in
 * the book's private storage and can only be added, never replaced or
 * removed, so evidence cannot be changed after the fact.
 */
class AttachEvidence
{
    public function __construct(
        private readonly BookkeepingRecorder $recorder,
        private readonly CreateContextAsset $assets,
    ) {}

    public function execute(Transaction $transaction, User $user, UploadedFile $file): Transaction
    {
        Gate::forUser($user)->authorize('attachEvidence', $transaction);
        $transaction->loadMissing('ledger.context');
        abort_if($transaction->assets()->count() >= 10, 422, 'An entry can have up to 10 files.');

        $asset = $this->assets->execute($transaction->ledger->context, $user, $file, 'owned');
        $actor = $this->recorder->actor($user);

        DB::table('bk_transaction_assets')->insert([
            'transaction_id' => $transaction->id,
            'asset_id' => $asset->id,
            'added_by_actor_id' => $actor->id,
            'kind' => 'receipt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recorder->audit($transaction->ledger, $actor, 'evidence.added', $transaction, after: ['asset' => $asset->uuid, 'filename' => $asset->original_filename]);

        return $transaction->refresh();
    }
}
