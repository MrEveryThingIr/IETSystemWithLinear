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
        Schema::create('group_membership_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acting_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->string('event', 80);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['group_id', 'created_at']);
            $table->index(['group_membership_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_membership_events');
    }
};
