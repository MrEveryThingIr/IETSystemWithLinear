<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('actors', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('user_id');
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->foreignId('archived_by_user_id')->nullable()->after('archived_at')->constrained('users')->restrictOnDelete();
            $table->text('archive_reason')->nullable()->after('archived_by_user_id');

            $table->index(['status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actors', function (Blueprint $table): void {
            $table->dropForeign(['archived_by_user_id']);
            $table->dropIndex(['status', 'id']);
            $table->dropColumn(['status', 'archived_at', 'archived_by_user_id', 'archive_reason']);
        });
    }
};
