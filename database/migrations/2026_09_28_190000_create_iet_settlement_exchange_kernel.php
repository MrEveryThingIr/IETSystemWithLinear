<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iet_valuation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('usd_pico_per_iet');
            $table->string('source', 32)->default('manual');
            $table->json('factors')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('created_by_actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->index(['effective_at', 'id'], 'iet_valuation_effective_index');
        });

        DB::table('iet_valuation_snapshots')->insert([
            'uuid' => (string) Str::uuid(),
            'usd_pico_per_iet' => 10000,
            'source' => 'initial',
            'factors' => json_encode([
                'x_percent' => '0.000001',
                'policy' => 'bootstrap',
            ], JSON_THROW_ON_ERROR),
            'note' => 'Initial IET internal settlement valuation.',
            'effective_at' => '2000-01-01 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('iet_exchange_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('direction', 24);
            $table->string('fiat_unit_code', 12)->default('USD');
            $table->unsignedBigInteger('fiat_amount_minor');
            $table->foreignId('iet_valuation_snapshot_id')
                ->constrained('iet_valuation_snapshots')->restrictOnDelete();
            $table->unsignedBigInteger('iet_amount_minor');
            $table->string('status', 24)->default('pending');
            $table->string('external_reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by_actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'id'], 'iet_exchange_user_status_index');
            $table->index(['status', 'id'], 'iet_exchange_status_index');
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->foreignId('iet_valuation_snapshot_id')->nullable()
                ->after('amount_minor')
                ->constrained('iet_valuation_snapshots')->restrictOnDelete();
            $table->unsignedBigInteger('iet_amount_minor')->nullable()
                ->after('iet_valuation_snapshot_id');

            $table->index(
                ['iet_valuation_snapshot_id', 'status'],
                'settlements_iet_valuation_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropIndex('settlements_iet_valuation_status_index');
            $table->dropConstrainedForeignId('iet_valuation_snapshot_id');
            $table->dropColumn('iet_amount_minor');
        });

        Schema::dropIfExists('iet_exchange_requests');
        Schema::dropIfExists('iet_valuation_snapshots');
    }
};
