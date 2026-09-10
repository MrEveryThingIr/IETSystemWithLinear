<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_role_change_requests', function (Blueprint $table): void {
            $table->timestamp('review_locked_at')->nullable()->after('reviewed_at');
            $table->index(['group_id', 'membership_id', 'status']);
        });
        Schema::table('group_invitations', function (Blueprint $table): void {
            $table->index(['group_id', 'revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('group_role_change_requests', ['group_id', 'membership_id', 'status'])) {
            Schema::table('group_role_change_requests', function (Blueprint $table): void {
                $table->dropIndex(['group_id', 'membership_id', 'status']);
            });
        }

        if (Schema::hasColumn('group_role_change_requests', 'review_locked_at')) {
            Schema::table('group_role_change_requests', function (Blueprint $table): void {
                $table->dropColumn('review_locked_at');
            });
        }

        if (! Schema::hasIndex('group_invitations', 'group_invitations_group_id_index')) {
            Schema::table('group_invitations', function (Blueprint $table): void {
                $table->index('group_id');
            });
        }

        if (Schema::hasIndex('group_invitations', ['group_id', 'revoked_at', 'expires_at'])) {
            Schema::table('group_invitations', function (Blueprint $table): void {
                $table->dropIndex(['group_id', 'revoked_at', 'expires_at']);
            });
        }
    }
};
