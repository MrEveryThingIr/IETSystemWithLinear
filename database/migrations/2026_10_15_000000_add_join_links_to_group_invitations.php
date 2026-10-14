<?php

use App\Support\PortableForeignKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_invitations', function (Blueprint $table): void {
            // review: the classic admission flow; direct: a join link that admits
            // immediately when the group has no agreements to accept.
            $table->string('join_mode', 16)->default('review')->after('content');
            $table->foreignId('granted_role_id')->nullable()->after('join_mode');
            $table->string('label', 120)->nullable()->after('granted_role_id');
            // Only platform user managers may issue links that let new people
            // create an account; other links are for existing accounts.
            $table->boolean('allows_registration')->default(false)->after('label');

            $table->foreign('granted_role_id', 'group_invitations_granted_role_fk')
                ->references('id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_invitations', function (Blueprint $table): void {
            PortableForeignKey::drop($table, 'group_invitations_granted_role_fk', ['granted_role_id']);
            $table->dropColumn(['join_mode', 'granted_role_id', 'label', 'allows_registration']);
        });
    }
};
