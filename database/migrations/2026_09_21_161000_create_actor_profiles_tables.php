<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->unique()->constrained('actors')->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('display_name', 120)->nullable();
            $table->string('headline', 180)->nullable();
            $table->text('bio')->nullable();
            $table->string('location_text', 180)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('visibility', 32)->default('private');
            $table->unsignedBigInteger('display_profile_image_id')->nullable();
            $table->timestamps();

            $table->index(['visibility', 'updated_at']);
        });

        Schema::create('actor_profile_images', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_profile_id')->constrained('actor_profiles')->cascadeOnDelete();
            $table->foreignId('asset_id')->unique()->constrained('assets')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['actor_profile_id', 'position']);
        });

        Schema::table('actor_profiles', function (Blueprint $table): void {
            $table->foreign('display_profile_image_id', 'actor_profile_display_image_fk')
                ->references('id')
                ->on('actor_profile_images')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $profileAssetIds = DB::table('actor_profile_images')->pluck('asset_id');

        Schema::table('actor_profiles', function (Blueprint $table): void {
            $table->dropForeign('actor_profile_display_image_fk');
        });

        Schema::dropIfExists('actor_profile_images');
        Schema::dropIfExists('actor_profiles');

        if ($profileAssetIds->isNotEmpty()) {
            DB::table('assets')->whereIn('id', $profileAssetIds)->delete();
        }
    }
};
