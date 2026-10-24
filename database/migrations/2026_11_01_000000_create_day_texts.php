<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Text of the day: short quotes, news and notices the superadmin writes for
 * the moving line in the top time bar. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('day_texts')) {
            return;
        }

        Schema::create('day_texts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kind', 16)->default('quote');
            // Locale code => text. Plain text only; rendered escaped.
            $table->json('texts');
            $table->string('attribution', 160)->nullable();
            $table->string('link_url', 500)->nullable();
            // Both empty: part of the daily rotation. Otherwise shown on
            // every day of the range (either end may be open).
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'starts_on', 'ends_on'], 'day_texts_active_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_texts');
    }
};
