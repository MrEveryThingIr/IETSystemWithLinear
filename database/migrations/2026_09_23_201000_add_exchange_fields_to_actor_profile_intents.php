<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->string('subject_kind', 32)->default('other')->after('kind');
            $table->string('arrangement_kind', 32)->default('other')->after('subject_kind');
            $table->string('exchange_preference', 48)->default('discuss_later')->after('arrangement_kind');
            $table->decimal('cash_min', 18, 2)->nullable()->after('quantity');
            $table->decimal('cash_max', 18, 2)->nullable()->after('cash_min');
            $table->string('currency_code', 3)->nullable()->after('cash_max');
            $table->string('cash_basis', 24)->nullable()->after('currency_code');
            $table->text('exchange_notes')->nullable()->after('cash_basis');

            $table->index(['subject_kind', 'arrangement_kind', 'status'], 'profile_intents_subject_arrangement_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->dropIndex('profile_intents_subject_arrangement_status_index');
            $table->dropColumn([
                'subject_kind',
                'arrangement_kind',
                'exchange_preference',
                'cash_min',
                'cash_max',
                'currency_code',
                'cash_basis',
                'exchange_notes',
            ]);
        });
    }
};
