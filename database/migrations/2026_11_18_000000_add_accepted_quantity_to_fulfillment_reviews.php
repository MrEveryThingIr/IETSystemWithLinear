<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reviewer may confirm less than was reported ("partly accepted"). The reported quantity never changes; the review
 * records the quantity confirmed. Null means the whole reported quantity was confirmed, so older rows stay valid as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_reviews', function (Blueprint $table): void {
            $table->decimal('accepted_quantity', 18, 4)->nullable()->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_reviews', function (Blueprint $table): void {
            $table->dropColumn('accepted_quantity');
        });
    }
};
