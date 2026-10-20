<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planner v1.6:
 *
 * planner_labels       a person's own plan labels with a colour ("Gym", "Clients").
 *                      Plans point at them through metadata.label_uuid.
 * meeting_requests     "let's find a time": the organizer offers time options.
 * meeting_invitees     who is asked: a user, or a guest reached by a private link
 *                      (name/email only) who may register later and claim it.
 * meeting_slots        time options, offered by the organizer or suggested by an
 *                      invitee.
 * meeting_votes        an invitee's yes / maybe / no for each option.
 *
 * When a time is agreed, an ordinary Plan is created in the organizer's and
 * each user invitee's own planner (metadata.meeting_uuid links them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_labels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('planner_labels_uuid_uq');
            $table->foreignId('actor_id');
            $table->string('name', 60);
            $table->string('color', 7);
            $table->timestamps();

            $table->unique(['actor_id', 'name'], 'planner_labels_actor_name_uq');
            $table->foreign('actor_id', 'planner_labels_actor_fk')->references('id')->on('actors')->cascadeOnDelete();
        });

        Schema::create('meeting_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('meeting_requests_uuid_uq');
            $table->foreignId('organizer_actor_id');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('location', 300)->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->string('timezone', 64);
            $table->string('status', 16)->default('open');
            $table->unsignedBigInteger('final_slot_id')->nullable();
            $table->foreignId('organizer_plan_id')->nullable();
            $table->string('label_uuid', 36)->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_actor_id', 'status'], 'meeting_requests_organizer_ix');
            $table->foreign('organizer_actor_id', 'meeting_requests_organizer_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('organizer_plan_id', 'meeting_requests_plan_fk')->references('id')->on('plans')->nullOnDelete();
        });

        Schema::create('meeting_invitees', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('meeting_invitees_uuid_uq');
            $table->foreignId('meeting_request_id');
            $table->foreignId('actor_id')->nullable();
            $table->string('name', 120);
            $table->string('email', 190)->nullable();
            $table->string('token_hash', 64)->nullable()->unique('meeting_invitees_token_uq');
            $table->text('token_encrypted')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('note', 1000)->nullable();
            $table->foreignId('plan_id')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_request_id', 'actor_id'], 'meeting_invitees_request_actor_uq');
            $table->index('actor_id', 'meeting_invitees_actor_ix');
            $table->foreign('meeting_request_id', 'meeting_invitees_request_fk')->references('id')->on('meeting_requests')->cascadeOnDelete();
            $table->foreign('actor_id', 'meeting_invitees_actor_fk')->references('id')->on('actors')->nullOnDelete();
            $table->foreign('plan_id', 'meeting_invitees_plan_fk')->references('id')->on('plans')->nullOnDelete();
        });

        Schema::create('meeting_slots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('meeting_slots_uuid_uq');
            $table->foreignId('meeting_request_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('suggested_by_invitee_id')->nullable();
            $table->string('status', 16)->default('offered');
            $table->timestamps();

            $table->index(['meeting_request_id', 'starts_at'], 'meeting_slots_request_start_ix');
            $table->foreign('meeting_request_id', 'meeting_slots_request_fk')->references('id')->on('meeting_requests')->cascadeOnDelete();
            $table->foreign('suggested_by_invitee_id', 'meeting_slots_invitee_fk')->references('id')->on('meeting_invitees')->nullOnDelete();
        });

        Schema::create('meeting_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_slot_id');
            $table->foreignId('meeting_invitee_id');
            $table->string('answer', 8);
            $table->timestamps();

            $table->unique(['meeting_slot_id', 'meeting_invitee_id'], 'meeting_votes_slot_invitee_uq');
            $table->foreign('meeting_slot_id', 'meeting_votes_slot_fk')->references('id')->on('meeting_slots')->cascadeOnDelete();
            $table->foreign('meeting_invitee_id', 'meeting_votes_invitee_fk')->references('id')->on('meeting_invitees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_votes');
        Schema::dropIfExists('meeting_slots');
        Schema::dropIfExists('meeting_invitees');
        Schema::dropIfExists('meeting_requests');
        Schema::dropIfExists('planner_labels');
    }
};
