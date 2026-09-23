<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_origins', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('supersedes_origin_id')->nullable()->constrained('development_origins')->restrictOnDelete();
            $table->string('source_type', 40);
            $table->text('source_url')->nullable();
            $table->string('title');
            $table->text('summary');
            $table->string('phase_key', 80)->nullable();
            $table->string('system_version', 80)->nullable();
            $table->string('branch', 160)->nullable();
            $table->char('baseline_commit_sha', 40)->nullable();
            $table->char('result_commit_sha', 40)->nullable();
            $table->json('repository_paths');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['phase_key', 'id']);
            $table->index(['system_version', 'id']);
            $table->index(['source_type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_origins');
    }
};
