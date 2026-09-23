<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invited_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('access_invitation_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('access_invitation_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained()->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['access_invitation_id','user_id'], 'access_invitation_user_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('access_invitation_acceptances');
        Schema::dropIfExists('access_invitations');
    }
};
