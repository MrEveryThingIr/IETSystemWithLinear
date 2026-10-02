<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_service_terms', function (Blueprint $table): void {
            $table->foreignId('reference_monetary_unit_id')->nullable()->after('monetary_unit_id');
            $table->unsignedBigInteger('reference_unit_rate_minor')->nullable()->after('unit_rate_minor');
            $table->unsignedBigInteger('reference_usd_amount_minor')->nullable()->after('reference_unit_rate_minor');
            $table->foreignId('reference_market_quote_id')->nullable()->after('reference_usd_amount_minor');
            $table->foreignId('iet_valuation_quote_id')->nullable()->after('reference_market_quote_id');

            $table->foreign('reference_monetary_unit_id', 'contract_service_terms_reference_unit_fk')
                ->references('id')->on('monetary_units')->restrictOnDelete();
            $table->foreign('reference_market_quote_id', 'contract_service_terms_reference_quote_fk')
                ->references('id')->on('market_quotes')->restrictOnDelete();
            $table->foreign('iet_valuation_quote_id', 'contract_service_terms_iet_quote_fk')
                ->references('id')->on('iet_valuation_quotes')->restrictOnDelete();
        });

        Schema::table('contract_settlement_batches', function (Blueprint $table): void {
            $table->foreignId('reference_monetary_unit_id')->nullable()->after('monetary_unit_id');
            $table->unsignedBigInteger('reference_amount_minor')->nullable()->after('amount_minor');
            $table->unsignedBigInteger('reference_usd_amount_minor')->nullable()->after('reference_amount_minor');
            $table->foreignId('reference_market_quote_id')->nullable()->after('reference_usd_amount_minor');
            $table->foreignId('iet_valuation_quote_id')->nullable()->after('reference_market_quote_id');

            $table->foreign('reference_monetary_unit_id', 'settlement_batches_reference_unit_fk')
                ->references('id')->on('monetary_units')->restrictOnDelete();
            $table->foreign('reference_market_quote_id', 'settlement_batches_reference_quote_fk')
                ->references('id')->on('market_quotes')->restrictOnDelete();
            $table->foreign('iet_valuation_quote_id', 'settlement_batches_iet_quote_fk')
                ->references('id')->on('iet_valuation_quotes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_settlement_batches', function (Blueprint $table): void {
            $table->dropForeign(['reference_monetary_unit_id']);
            $table->dropForeign(['reference_market_quote_id']);
            $table->dropForeign(['iet_valuation_quote_id']);
            $table->dropColumn([
                'reference_monetary_unit_id',
                'reference_amount_minor',
                'reference_usd_amount_minor',
                'reference_market_quote_id',
                'iet_valuation_quote_id',
            ]);
        });

        Schema::table('contract_service_terms', function (Blueprint $table): void {
            $table->dropForeign(['reference_monetary_unit_id']);
            $table->dropForeign(['reference_market_quote_id']);
            $table->dropForeign(['iet_valuation_quote_id']);
            $table->dropColumn([
                'reference_monetary_unit_id',
                'reference_unit_rate_minor',
                'reference_usd_amount_minor',
                'reference_market_quote_id',
                'iet_valuation_quote_id',
            ]);
        });
    }
};
