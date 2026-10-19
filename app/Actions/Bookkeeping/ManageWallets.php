<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\CategoryDirection;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\WalletKind;
use App\Models\Bookkeeping\Category;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Wallet;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\LocalizedNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Wallets and categories of a personal book. */
class ManageWallets
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function addWallet(Ledger $ledger, User $user, string $name, WalletKind $kind, string|int|null $openingBalance = null, ?string $maskedIdentifier = null): Wallet
    {
        $this->authorize($ledger, $user);
        $name = Str::squish($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['walletName' => __('bookkeeping.validation.name_required')]);
        }

        $opening = 0;
        if ($openingBalance !== null && $openingBalance !== '') {
            $digits = (string) preg_replace('/[\s,٬،]/u', '', LocalizedNumber::digits((string) $openingBalance));
            if (preg_match('/^\d{1,18}$/', $digits) !== 1) {
                throw ValidationException::withMessages(['walletOpening' => __('bookkeeping.validation.amount')]);
            }
            $opening = (int) $digits;
        }

        // Only the last four characters of an account or card number are kept.
        $masked = $maskedIdentifier !== null ? (string) preg_replace('/\D/', '', LocalizedNumber::digits($maskedIdentifier)) : '';
        $masked = $masked !== '' ? '•••• '.substr($masked, -4) : null;

        $wallet = Wallet::query()->create([
            'ledger_id' => $ledger->id,
            'name' => $name,
            'kind' => $kind,
            'opening_balance' => $opening,
            'masked_identifier' => $masked,
        ]);

        $this->recorder->audit($ledger, $this->recorder->actor($user), 'wallet.added', after: ['wallet' => $wallet->uuid, 'opening_balance' => $opening]);

        return $wallet;
    }

    public function archiveWallet(Wallet $wallet, User $user): Wallet
    {
        $this->authorize($wallet->ledger, $user);
        abort_if($wallet->ledger->wallets()->whereNull('archived_at')->count() <= 1, 422, 'Keep at least one wallet.');
        $wallet->forceFill(['archived_at' => now()])->save();
        $this->recorder->audit($wallet->ledger, $this->recorder->actor($user), 'wallet.archived', after: ['wallet' => $wallet->uuid]);

        return $wallet;
    }

    public function addCategory(Ledger $ledger, User $user, CategoryDirection $direction, string $name): Category
    {
        $this->authorize($ledger, $user);
        $name = Str::squish($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages(['categoryName' => __('bookkeeping.validation.name_required')]);
        }

        return Category::query()->firstOrCreate(
            ['ledger_id' => $ledger->id, 'direction' => $direction->value, 'name' => $name],
        );
    }

    private function authorize(Ledger $ledger, User $user): void
    {
        abort_unless($ledger->kind === LedgerKind::Personal, 422);
        Gate::forUser($user)->authorize('record', $ledger);
    }
}
