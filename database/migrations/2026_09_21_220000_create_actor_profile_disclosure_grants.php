<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actor_profile_disclosure_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_profile_id')->constrained('actor_profiles')->cascadeOnDelete();
            $table->foreignId('grantee_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('purpose', 180)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['actor_profile_id', 'revoked_at', 'expires_at'], 'profile_disclosures_owner_active_index');
            $table->index(['grantee_actor_id', 'revoked_at', 'expires_at'], 'profile_disclosures_grantee_active_index');
        });

        Schema::create('actor_profile_disclosure_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grant_id')->constrained('actor_profile_disclosure_grants')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('item_key', 255);
            $table->timestamps();

            $table->unique(['grant_id', 'item_key'], 'profile_disclosure_items_unique');
            $table->index(['kind', 'item_key'], 'profile_disclosure_items_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actor_profile_disclosure_items');
        Schema::dropIfExists('actor_profile_disclosure_grants');
    }
};
