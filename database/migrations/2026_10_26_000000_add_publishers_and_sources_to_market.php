<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.9 — Needs and Offers published by a person, a business or a group, with
 * explicit sources (capability, catalog item and exact version), photos and short videos
 * (through the shared Asset pipeline) and a negotiation flag. Additive; existing
 * records are backfilled from their own data and nothing is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->foreignId('publisher_actor_id')->nullable()->after('actor_profile_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('publisher_business_id')->nullable()->after('publisher_actor_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('publisher_group_id')->nullable()->after('publisher_business_id')->constrained('groups')->restrictOnDelete();
            $table->foreignId('capability_id')->nullable()->after('concept_id')->constrained('capabilities')->nullOnDelete();
            $table->foreignId('business_listing_id')->nullable()->after('capability_id')->constrained('business_listings')->nullOnDelete();
            $table->foreignId('business_listing_version_id')->nullable()->after('business_listing_id')->constrained('business_listing_versions')->nullOnDelete();
            $table->boolean('negotiable')->default(true)->after('cash_basis');

            $table->index(['publisher_business_id', 'status']);
            $table->index(['publisher_group_id', 'status']);
            $table->index(['publisher_actor_id', 'status']);
        });

        // Photos and short videos of a Need/Offer (files go through the shared Asset pipeline).
        Schema::create('actor_profile_intent_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_profile_intent_id')->constrained('actor_profile_intents')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('caption', 500)->nullable();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->index(['actor_profile_intent_id', 'position'], 'intent_media_order_idx');
        });

        Schema::table('business_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('concept_id')->nullable()->after('parent_id');
            $table->index('concept_id');
        });

        // Every existing Need/Offer was published by the person whose profile holds it.
        DB::table('actor_profile_intents')->whereNull('publisher_actor_id')->whereNull('publisher_business_id')->whereNull('publisher_group_id')
            ->update(['publisher_actor_id' => DB::raw('(select actor_id from actor_profiles where actor_profiles.id = actor_profile_intents.actor_profile_id)')]);

        // Business offers and client needs recorded their business only in metadata: make it explicit.
        DB::table('actor_profile_intents')->whereNotNull('metadata')->orderBy('id')->each(function ($intent): void {
            $metadata = json_decode((string) $intent->metadata, true);
            if (! is_array($metadata) || ! in_array($metadata['source'] ?? null, ['business_listing_version', 'business_contact_need'], true)) {
                return;
            }
            $businessId = DB::table('businesses')->where('uuid', $metadata['business_uuid'] ?? '')->value('id');
            if ($businessId === null) {
                return;
            }
            $listingId = DB::table('business_listings')->where('uuid', $metadata['business_listing_uuid'] ?? '')->value('id');
            $versionId = DB::table('business_listing_versions')->where('uuid', $metadata['business_listing_version_uuid'] ?? '')->value('id');
            DB::table('actor_profile_intents')->where('id', $intent->id)->update([
                'publisher_actor_id' => null,
                'publisher_business_id' => $businessId,
                'business_listing_id' => $listingId,
                'business_listing_version_id' => $versionId,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actor_profile_intent_media');
        Schema::table('business_categories', function (Blueprint $table): void {
            $table->dropIndex(['concept_id']);
        });
        Schema::table('business_categories', fn (Blueprint $table) => $table->dropColumn('concept_id'));
        // Foreign keys first: MySQL/MariaDB will not drop an index a foreign key still uses.
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            foreach (['publisher_actor_id', 'publisher_business_id', 'publisher_group_id', 'capability_id', 'business_listing_id', 'business_listing_version_id'] as $column) {
                $table->dropForeign([$column]);
            }
        });
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->dropIndex(['publisher_business_id', 'status']);
            $table->dropIndex(['publisher_group_id', 'status']);
            $table->dropIndex(['publisher_actor_id', 'status']);
        });
        Schema::table('actor_profile_intents', fn (Blueprint $table) => $table->dropColumn([
            'publisher_actor_id', 'publisher_business_id', 'publisher_group_id', 'capability_id',
            'business_listing_id', 'business_listing_version_id', 'negotiable',
        ]));
    }
};
