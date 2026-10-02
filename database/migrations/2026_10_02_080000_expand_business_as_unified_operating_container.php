<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('businesses', 'slug')) {
                $table->string('slug', 180)->nullable()->unique();
            }
            if (! Schema::hasColumn('businesses', 'timezone')) {
                $table->string('timezone', 80)->nullable();
            }
            if (! Schema::hasColumn('businesses', 'default_monetary_unit_id')) {
                $table->foreignId('default_monetary_unit_id')->nullable();
                $table->foreign('default_monetary_unit_id', 'business_default_unit_fk')
                    ->references('id')->on('monetary_units')->nullOnDelete();
            }
            if (! Schema::hasColumn('businesses', 'settings')) {
                $table->json('settings')->nullable();
            }
        });

        if (! Schema::hasTable('business_contexts')) {
            Schema::create('business_contexts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('context_id')->unique('business_context_context_uq');
                $table->foreignId('business_id')->unique('business_context_business_uq');
                $table->timestamps();

                $table->foreign('context_id', 'business_context_context_fk')
                    ->references('id')->on('contexts')->restrictOnDelete();
                $table->foreign('business_id', 'business_context_business_fk')
                    ->references('id')->on('businesses')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('business_categories')) {
            Schema::create('business_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('business_category_uuid_uq');
                $table->foreignId('business_id');
                $table->foreignId('parent_id')->nullable();
                $table->string('name', 180);
                $table->string('slug', 180);
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->foreign('business_id', 'business_category_business_fk')
                    ->references('id')->on('businesses')->cascadeOnDelete();
                $table->foreign('parent_id', 'business_category_parent_fk')
                    ->references('id')->on('business_categories')->nullOnDelete();
                $table->unique(['business_id', 'slug'], 'business_category_slug_uq');
            });
        }

        if (! Schema::hasTable('business_listings')) {
            Schema::create('business_listings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('business_listing_uuid_uq');
                $table->foreignId('business_id');
                $table->foreignId('business_category_id')->nullable();
                $table->string('listing_type', 40)->index();
                $table->string('status', 32)->default('draft')->index();
                $table->string('visibility', 20)->default('private')->index();
                $table->timestamps();

                $table->foreign('business_id', 'business_listing_business_fk')
                    ->references('id')->on('businesses')->cascadeOnDelete();
                $table->foreign('business_category_id', 'business_listing_category_fk')
                    ->references('id')->on('business_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('business_listing_versions')) {
            Schema::create('business_listing_versions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('business_listing_version_uuid_uq');
                $table->foreignId('business_listing_id');
                $table->unsignedInteger('version_number');
                $table->string('title', 220);
                $table->string('slug', 220);
                $table->string('short_description', 500)->nullable();
                $table->longText('description')->nullable();
                $table->json('structured_data')->nullable();
                $table->foreignId('created_by_actor_id');
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();

                $table->foreign('business_listing_id', 'business_listing_version_listing_fk')
                    ->references('id')->on('business_listings')->cascadeOnDelete();
                $table->foreign('created_by_actor_id', 'business_listing_version_actor_fk')
                    ->references('id')->on('actors')->restrictOnDelete();
                $table->unique(['business_listing_id', 'version_number'], 'business_listing_version_number_uq');
            });
        }

        Schema::table('business_listings', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_listings', 'current_version_id')) {
                $table->foreignId('current_version_id')->nullable();
                $table->foreign('current_version_id', 'business_listing_current_version_fk')
                    ->references('id')->on('business_listing_versions')->nullOnDelete();
            }
            if (! Schema::hasColumn('business_listings', 'published_version_id')) {
                $table->foreignId('published_version_id')->nullable();
                $table->foreign('published_version_id', 'business_listing_published_version_fk')
                    ->references('id')->on('business_listing_versions')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('business_price_versions')) {
            Schema::create('business_price_versions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('business_price_version_uuid_uq');
                $table->foreignId('business_listing_id');
                $table->foreignId('business_listing_version_id')->nullable();
                $table->foreignId('monetary_unit_id');
                $table->string('price_type', 48)->index();
                $table->bigInteger('amount_minor');
                $table->string('basis', 80)->nullable();
                $table->string('visibility', 20)->default('members')->index();
                $table->timestamp('valid_from')->index();
                $table->timestamp('valid_until')->nullable();
                $table->foreignId('created_by_actor_id');
                $table->string('reason', 500)->nullable();
                $table->timestamps();

                $table->foreign('business_listing_id', 'business_price_listing_fk')
                    ->references('id')->on('business_listings')->cascadeOnDelete();
                $table->foreign('business_listing_version_id', 'business_price_version_fk')
                    ->references('id')->on('business_listing_versions')->nullOnDelete();
                $table->foreign('monetary_unit_id', 'business_price_unit_fk')
                    ->references('id')->on('monetary_units')->restrictOnDelete();
                $table->foreign('created_by_actor_id', 'business_price_actor_fk')
                    ->references('id')->on('actors')->restrictOnDelete();
                $table->index(['business_listing_id', 'price_type', 'valid_from'], 'business_price_timeline_ix');
            });
        }

        if (! Schema::hasTable('business_property_details')) {
            Schema::create('business_property_details', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_listing_version_id')->unique('business_property_version_uq');
                $table->string('transaction_mode', 32)->nullable()->index();
                $table->string('property_class', 48)->nullable()->index();
                $table->string('property_subtype', 80)->nullable();
                $table->text('exact_address')->nullable();
                $table->string('public_area', 180)->nullable();
                $table->decimal('land_area', 12, 2)->nullable();
                $table->decimal('construction_area', 12, 2)->nullable();
                $table->decimal('width', 10, 2)->nullable();
                $table->decimal('length', 10, 2)->nullable();
                $table->unsignedTinyInteger('frontage_count')->nullable();
                $table->unsignedSmallInteger('built_year')->nullable();
                $table->string('built_year_calendar', 16)->nullable();
                $table->unsignedSmallInteger('building_age_years')->nullable();
                $table->string('building_condition', 48)->nullable();
                $table->unsignedTinyInteger('bedrooms')->nullable();
                $table->string('bedrooms_note', 255)->nullable();
                $table->string('cabinet_type', 60)->nullable();
                $table->boolean('has_false_ceiling')->nullable();
                $table->string('false_ceiling_note', 255)->nullable();
                $table->string('heating_system', 80)->nullable();
                $table->string('cooling_system', 80)->nullable();
                $table->string('yard_finish', 80)->nullable();
                $table->string('flooring_type', 80)->nullable();
                $table->string('flooring_note', 255)->nullable();
                $table->boolean('has_parking')->nullable();
                $table->string('parking_type', 60)->nullable();
                $table->unsignedSmallInteger('parking_spaces')->nullable();
                $table->unsignedSmallInteger('car_capacity')->nullable();
                $table->unsignedSmallInteger('motorbike_capacity')->nullable();
                $table->string('parking_note', 255)->nullable();
                $table->string('roof_finish', 80)->nullable();
                $table->string('roof_note', 255)->nullable();
                $table->boolean('roof_has_parapet')->nullable();
                $table->boolean('has_western_toilet')->nullable();
                $table->boolean('has_iranian_toilet')->nullable();
                $table->json('facilities')->nullable();
                $table->timestamps();

                $table->foreign('business_listing_version_id', 'business_property_version_fk')
                    ->references('id')->on('business_listing_versions')->cascadeOnDelete();
            });
        }

        Schema::table('public_intake_portals', function (Blueprint $table): void {
            if (! Schema::hasColumn('public_intake_portals', 'business_id')) {
                $table->foreignId('business_id')->nullable();
                $table->foreign('business_id', 'public_intake_business_fk')
                    ->references('id')->on('businesses')->nullOnDelete();
            }
        });

        Schema::table('public_real_estate_cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('public_real_estate_cases', 'business_listing_id')) {
                $table->foreignId('business_listing_id')->nullable();
                $table->foreign('business_listing_id', 'real_estate_case_listing_fk')
                    ->references('id')->on('business_listings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('public_real_estate_cases', 'business_listing_id')) {
            Schema::table('public_real_estate_cases', function (Blueprint $table): void {
                $table->dropForeign(['business_listing_id']);
                $table->dropColumn('business_listing_id');
            });
        }

        if (Schema::hasColumn('public_intake_portals', 'business_id')) {
            Schema::table('public_intake_portals', function (Blueprint $table): void {
                $table->dropForeign(['business_id']);
                $table->dropColumn('business_id');
            });
        }

        Schema::dropIfExists('business_property_details');
        Schema::dropIfExists('business_price_versions');

        if (Schema::hasTable('business_listings')) {
            Schema::table('business_listings', function (Blueprint $table): void {
                if (Schema::hasColumn('business_listings', 'current_version_id')) {
                    $table->dropForeign(['current_version_id']);
                    $table->dropColumn('current_version_id');
                }
                if (Schema::hasColumn('business_listings', 'published_version_id')) {
                    $table->dropForeign(['published_version_id']);
                    $table->dropColumn('published_version_id');
                }
            });
        }

        Schema::dropIfExists('business_listing_versions');
        Schema::dropIfExists('business_listings');
        Schema::dropIfExists('business_categories');
        Schema::dropIfExists('business_contexts');

        Schema::table('businesses', function (Blueprint $table): void {
            if (Schema::hasColumn('businesses', 'default_monetary_unit_id')) {
                $table->dropForeign(['default_monetary_unit_id']);
            }

            $columns = array_filter([
                Schema::hasColumn('businesses', 'slug') ? 'slug' : null,
                Schema::hasColumn('businesses', 'timezone') ? 'timezone' : null,
                Schema::hasColumn('businesses', 'default_monetary_unit_id') ? 'default_monetary_unit_id' : null,
                Schema::hasColumn('businesses', 'settings') ? 'settings' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
