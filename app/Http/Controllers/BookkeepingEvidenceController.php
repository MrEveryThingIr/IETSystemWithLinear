<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Support\AssetHttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Evidence files of a book entry, for the book's parties only (route checks `view`). */
class BookkeepingEvidenceController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger, Transaction $transaction, Asset $asset): Response
    {
        abort_unless((int) $transaction->ledger_id === (int) $ledger->id, 404);
        abort_unless(
            DB::table('bk_transaction_assets')
                ->where('transaction_id', $transaction->id)
                ->where('asset_id', $asset->id)
                ->exists(),
            404,
        );
        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);

        return $request->boolean('download')
            ? AssetHttpResponse::download($asset)
            : AssetHttpResponse::inline($asset);
    }
}
