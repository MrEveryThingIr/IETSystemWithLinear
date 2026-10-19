<?php

use App\Support\PortableForeignKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the legacy financial system: double-entry accounting (ledgers,
 * accounts, journals), contract financial obligations and settlements,
 * money intentions, and the IET exchange / valuation / market-quote tables.
 * Bookkeeping (bk_* tables) replaces it.
 *
 * Irreversible by design: take the database backup from the upgrade steps
 * before running it. On a fresh install these tables never existed, so every
 * step is guarded. down() does not recreate them.
 */
return new class extends Migration
{
    /** Children first. */
    private const TABLES = [
        'iet_internal_charges',
        'iet_priced_financial_obligations',
        'iet_exchange_requests',
        'financial_obligation_events',
        'settlements',
        'contract_settlement_batches',
        'financial_obligations',
        'journal_lines',
        'journal_entries',
        'accounts',
        'ledgers',
        'money_intentions',
        'market_quotes',
        'quote_sources',
        'economic_instruments',
        'iet_valuation_quotes',
    ];

    /** Reference-pricing snapshot columns on contract service terms. */
    private const TERM_FOREIGN_KEYS = [
        'contract_service_terms_reference_unit_fk' => 'reference_monetary_unit_id',
        'contract_service_terms_reference_quote_fk' => 'reference_market_quote_id',
        'contract_service_terms_iet_quote_fk' => 'iet_valuation_quote_id',
    ];

    private const TERM_COLUMNS = [
        'reference_monetary_unit_id',
        'reference_unit_rate_minor',
        'reference_usd_amount_minor',
        'reference_market_quote_id',
        'iet_valuation_quote_id',
    ];

    public function up(): void
    {
        if (Schema::hasTable('contract_service_terms') && Schema::hasColumn('contract_service_terms', 'reference_market_quote_id')) {
            Schema::table('contract_service_terms', function (Blueprint $table): void {
                foreach (self::TERM_FOREIGN_KEYS as $name => $column) {
                    PortableForeignKey::drop($table, $name, [$column]);
                }
            });

            Schema::table('contract_service_terms', function (Blueprint $table): void {
                $table->dropColumn(self::TERM_COLUMNS);
            });
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::TABLES as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Publication grants for surfaces that no longer exist. The "money"
        // surface is kept and now opens Bookkeeping.
        if (Schema::hasTable('feature_surface_grants')) {
            DB::table('feature_surface_grants')->whereIn('surface_key', ['accounting', 'exchange'])->delete();
        }
    }

    public function down(): void
    {
        // Intentionally empty: the legacy financial system is not restored.
    }
};
