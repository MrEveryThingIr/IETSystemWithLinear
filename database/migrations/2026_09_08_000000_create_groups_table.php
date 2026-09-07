<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained()->restrictOnDelete();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['group_id', 'actor_id']);
        });

        Schema::create('group_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('email')->nullable();
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_invitations');
        Schema::dropIfExists('group_memberships');
        Schema::dropIfExists('groups');
    }
};
