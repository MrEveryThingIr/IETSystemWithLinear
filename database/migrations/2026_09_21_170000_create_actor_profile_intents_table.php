<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actor_profile_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_profile_id')->constrained('actor_profiles')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('concepts')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('title', 180)->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 64)->nullable();
            $table->string('location_text', 255)->nullable();
            $table->string('origin_text', 255)->nullable();
            $table->string('destination_text', 255)->nullable();
            $table->boolean('round_trip')->default(false);
            $table->unsignedSmallInteger('return_after_days')->nullable();
            $table->string('schedule_kind', 24)->default('once');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->json('recurrence_weekdays')->nullable();
            $table->unsignedTinyInteger('recurrence_day_of_month')->nullable();
            $table->time('time_window_start')->nullable();
            $table->time('time_window_end')->nullable();
            $table->string('visibility', 32)->default('inherited');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['actor_profile_id', 'kind', 'status'], 'profile_intents_profile_kind_status_index');
            $table->index(['concept_id', 'kind', 'status'], 'profile_intents_concept_kind_status_index');
            $table->index(['visibility', 'status'], 'profile_intents_visibility_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actor_profile_intents');
    }
};
