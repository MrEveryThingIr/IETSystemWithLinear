<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_ownership_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_membership_id')->constrained('group_memberships')->restrictOnDelete();
            $table->foreignId('to_membership_id')->constrained('group_memberships')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('pending_group_id')->nullable()->unique();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['to_membership_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_ownership_transfer_requests');
    }
};
