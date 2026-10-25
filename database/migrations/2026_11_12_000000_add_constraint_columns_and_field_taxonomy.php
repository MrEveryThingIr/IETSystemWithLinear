<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A Need may search by an acceptable range; an Offer states one value. Open bounds are null.
        Schema::table('intent_search_values', function (Blueprint $table): void {
            $table->decimal('value_number_min', 24, 6)->nullable();
            $table->decimal('value_number_max', 24, 6)->nullable();
        });
        // Shared taxonomy: stable groups in a tree, with append-only, versioned membership of fields.
        // A field belongs to many groups by reference; it is never copied.
        Schema::create('intent_field_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('group_key', 64);
            $table->unsignedInteger('version');
            $table->string('parent_key', 64)->nullable();
            $table->string('status', 16)->default('published');
            $table->json('labels');
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('proposal_id')->nullable();
            $table->dateTime('published_at');
            $table->dateTime('retired_at')->nullable();
            $table->unique(['group_key', 'version'], 'intent_field_group_version_unique');
        });
        Schema::create('intent_field_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('group_key', 64);
            $table->string('field_key', 64);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('proposal_id')->nullable();
            $table->dateTime('added_at');
            $table->dateTime('removed_at')->nullable();
            $table->index(['group_key', 'removed_at'], 'intent_field_membership_group_idx');
            $table->index(['field_key', 'removed_at'], 'intent_field_membership_field_idx');
        });
        // A field definition may be superseded by a successor with a different meaning (for example a
        // corrected type or unit). The old key stays resolvable for published content.
        Schema::table('intent_field_definitions', function (Blueprint $table): void {
            $table->string('superseded_by', 64)->nullable();
            $table->json('hidden_options')->nullable();
        });
        // Personal groups may nest one level (a group inside a group); a composition remembers its side and arrangement.
        Schema::table('intent_detail_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->json('context')->nullable();
        });
        // Approved applicability rules: where a field should be hidden or belongs to one side only.
        Schema::create('intent_field_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('field_key', 64);
            $table->string('topic', 24)->nullable();
            $table->string('intention', 8)->nullable();
            $table->string('arrangement', 24)->nullable();
            $table->string('effect', 16);
            $table->json('condition')->nullable();
            $table->foreignId('proposal_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('retired_at')->nullable();
            $table->index(['field_key', 'retired_at'], 'intent_field_rule_field_idx');
        });
        // Proposals carry what kind of change they are and where they were made.
        Schema::table('intent_field_proposals', function (Blueprint $table): void {
            $table->string('change_type', 32)->nullable();
            $table->json('context')->nullable();
            $table->text('explanation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_field_rules');
        foreach ([['intent_field_proposals', ['change_type', 'context', 'explanation']], ['intent_detail_templates', ['parent_id', 'context']],
            ['intent_field_definitions', ['superseded_by', 'hidden_options']], ['intent_search_values', ['value_number_min', 'value_number_max']]] as [$table, $columns]) {
            if (Schema::hasColumn($table, $columns[0])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($columns));
            }
        }
        Schema::dropIfExists('intent_field_memberships');
        Schema::dropIfExists('intent_field_groups');
    }
};
