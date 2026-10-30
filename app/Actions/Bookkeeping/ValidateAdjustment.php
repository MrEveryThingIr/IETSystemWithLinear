<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Transaction;
use App\Support\Bookkeeping\BalanceCalculator;
use Illuminate\Validation\ValidationException;

final class ValidateAdjustment
{
    public function execute(Transaction $adjustment): void
    {
        if ($adjustment->type !== TransactionType::Adjustment || $adjustment->direction !== AdjustmentDirection::Decrease) {
            return;
        }
        $source = $adjustment->related;
        if ($source === null || $source->status !== TransactionStatus::Confirmed || (int) $adjustment->amount > app(BalanceCalculator::class)->obligation($source, currentRead: true)['amount']) {
            throw ValidationException::withMessages(['adjustAmount' => __('credit.validation.capacity')]);
        }
    }
}
