<?php

use App\Actions\Market\BackfillIntentVersions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intent_details', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_profile_intent_id')->constrained()->restrictOnDelete();
            $table->string('canonical_key', 64)->nullable();
            $table->string('label', 120);
            $table->string('type', 32);
            $table->json('payload');
            $table->string('unit', 64)->nullable();
            $table->string('group_label', 120)->nullable();
            $table->unsignedInteger('position');
            $table->string('visibility', 16)->default('same');
            $table->timestamps();
            $table->index(['actor_profile_intent_id', 'position'], 'intent_detail_order_idx');
        });
        Schema::create('intent_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_profile_intent_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('published_by_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->dateTime('published_at');
            $table->json('snapshot');
            $table->char('content_hash', 64);
            $table->text('search_text');
            $table->unique(['actor_profile_intent_id', 'version'], 'intent_version_number_unique');
        });
        Schema::create('intent_search_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intent_version_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_key', 64);
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_number', 24, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->date('value_date_end')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->index(['canonical_key', 'value_number'], 'intent_search_number_idx');
            $table->index(['canonical_key', 'value_text'], 'intent_search_text_idx');
            $table->index(['canonical_key', 'value_date'], 'intent_search_date_idx');
        });
        Schema::create('intent_detail_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->foreignId('owner_business_id')->nullable()->constrained('businesses')->restrictOnDelete();
            $table->string('name', 120);
            $table->json('fields');
            $table->timestamps();
        });
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->foreignId('published_version_id')->nullable()->constrained('intent_versions')->restrictOnDelete();
            $table->json('working_copy')->nullable();
        });
        Schema::table('actor_profile_intent_media', function (Blueprint $table): void {
            $table->string('visibility', 16)->default('same');
        });
        Schema::table('relationships', function (Blueprint $table): void {
            $table->foreignId('originating_intent_version_id')->nullable()->constrained('intent_versions')->restrictOnDelete();
        });
        app(BackfillIntentVersions::class)->execute();
    }

    public function down(): void
    {
        Schema::table('relationships', function (Blueprint $table): void {
            $table->dropForeign(['originating_intent_version_id']);
            $table->dropColumn('originating_intent_version_id');
        });
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->dropForeign(['published_version_id']);
            $table->dropColumn(['published_version_id', 'working_copy']);
        });
        Schema::table('actor_profile_intent_media', fn (Blueprint $table) => $table->dropColumn('visibility'));
        Schema::dropIfExists('intent_search_values');
        Schema::dropIfExists('intent_detail_templates');
        Schema::dropIfExists('intent_details');
        Schema::dropIfExists('intent_versions');
    }
};
