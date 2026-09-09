<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_role_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('group_memberships')->cascadeOnDelete();
            $table->foreignId('requested_role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_role_change_requests');
    }
};
