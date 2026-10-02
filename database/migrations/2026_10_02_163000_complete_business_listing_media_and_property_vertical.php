<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_listings', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_listings', 'business_contact_id')) {
                $table->foreignId('business_contact_id')->nullable();
                $table->foreign('business_contact_id', 'business_listing_contact_fk')
                    ->references('id')->on('business_contacts')->nullOnDelete();
            }

            if (! Schema::hasColumn('business_listings', 'availability_status')) {
                $table->string('availability_status', 32)->default('available')->index();
            }

            if (! Schema::hasColumn('business_listings', 'available_from')) {
                $table->timestamp('available_from')->nullable();
            }

            if (! Schema::hasColumn('business_listings', 'available_until')) {
                $table->timestamp('available_until')->nullable();
            }

            if (! Schema::hasColumn('business_listings', 'simple_office_mode')) {
                $table->boolean('simple_office_mode')->default(false);
            }
        });

        Schema::table('business_listing_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_listing_versions', 'presentation_content_id')) {
                $table->foreignId('presentation_content_id')->nullable();
                $table->foreign('presentation_content_id', 'business_listing_presentation_content_fk')
                    ->references('id')->on('space_contents')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('business_listing_media')) {
            Schema::create('business_listing_media', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('business_listing_media_uuid_uq');
                $table->foreignId('business_listing_version_id');
                $table->foreignId('asset_id');
                $table->string('role', 24)->default('gallery')->index();
                $table->unsignedInteger('position')->default(0);
                $table->string('caption', 1000)->nullable();
                $table->string('visibility', 20)->default('public')->index();
                $table->foreignId('created_by_actor_id');
                $table->timestamps();

                $table->foreign('business_listing_version_id', 'business_listing_media_version_fk')
                    ->references('id')->on('business_listing_versions')->cascadeOnDelete();
                $table->foreign('asset_id', 'business_listing_media_asset_fk')
                    ->references('id')->on('assets')->restrictOnDelete();
                $table->foreign('created_by_actor_id', 'business_listing_media_actor_fk')
                    ->references('id')->on('actors')->restrictOnDelete();
                $table->unique(
                    ['business_listing_version_id', 'asset_id'],
                    'business_listing_media_asset_uq'
                );
                $table->index(
                    ['business_listing_version_id', 'position'],
                    'business_listing_media_order_ix'
                );
            });
        }

        Schema::table('business_property_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_property_details', 'floor_number')) {
                $table->smallInteger('floor_number')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'total_floors')) {
                $table->unsignedSmallInteger('total_floors')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'unit_number')) {
                $table->string('unit_number', 40)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'units_per_floor')) {
                $table->unsignedSmallInteger('units_per_floor')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'has_elevator')) {
                $table->boolean('has_elevator')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'has_storage')) {
                $table->boolean('has_storage')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'storage_area')) {
                $table->decimal('storage_area', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'has_balcony')) {
                $table->boolean('has_balcony')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'balcony_area')) {
                $table->decimal('balcony_area', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'orientation')) {
                $table->string('orientation', 40)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'deed_type')) {
                $table->string('deed_type', 80)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'usage_type')) {
                $table->string('usage_type', 80)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'occupancy_status')) {
                $table->string('occupancy_status', 48)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'utilities')) {
                $table->json('utilities')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'public_notes')) {
                $table->text('public_notes')->nullable();
            }
            if (! Schema::hasColumn('business_property_details', 'private_notes')) {
                $table->text('private_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_property_details', function (Blueprint $table): void {
            $columns = [
                'floor_number',
                'total_floors',
                'unit_number',
                'units_per_floor',
                'has_elevator',
                'has_storage',
                'storage_area',
                'has_balcony',
                'balcony_area',
                'orientation',
                'deed_type',
                'usage_type',
                'occupancy_status',
                'utilities',
                'latitude',
                'longitude',
                'public_notes',
                'private_notes',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('business_property_details', $column),
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });

        Schema::dropIfExists('business_listing_media');

        if (Schema::hasColumn('business_listing_versions', 'presentation_content_id')) {
            Schema::table('business_listing_versions', function (Blueprint $table): void {
                $table->dropForeign(['presentation_content_id']);
                $table->dropColumn('presentation_content_id');
            });
        }

        Schema::table('business_listings', function (Blueprint $table): void {
            if (Schema::hasColumn('business_listings', 'business_contact_id')) {
                $table->dropForeign(['business_contact_id']);
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('business_listings', 'business_contact_id') ? 'business_contact_id' : null,
                Schema::hasColumn('business_listings', 'availability_status') ? 'availability_status' : null,
                Schema::hasColumn('business_listings', 'available_from') ? 'available_from' : null,
                Schema::hasColumn('business_listings', 'available_until') ? 'available_until' : null,
                Schema::hasColumn('business_listings', 'simple_office_mode') ? 'simple_office_mode' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
