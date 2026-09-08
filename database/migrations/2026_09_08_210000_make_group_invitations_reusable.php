<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_invitations', function (Blueprint $table): void {
            $table->unsignedInteger('max_uses')->nullable()->after('expires_at');
            $table->unsignedInteger('uses_count')->default(0)->after('max_uses');
            $table->timestamp('revoked_at')->nullable()->after('uses_count');
        });

        Schema::create('group_invitation_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_invitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accepted_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['group_invitation_id', 'accepted_by_actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_invitation_acceptances');
        Schema::table('group_invitations', function (Blueprint $table): void {
            $table->dropColumn(['max_uses', 'uses_count', 'revoked_at']);
        });
    }
};
