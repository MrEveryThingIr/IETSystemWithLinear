<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_points')) {
            Schema::create('contact_points', function (Blueprint $table): void {
                $table->id();

                $table->string('contactable_type', 120);
                $table->unsignedBigInteger('contactable_id');

                $table->string('kind', 24)->index();
                $table->string('label', 80)->nullable();
                $table->string('value', 500);
                $table->string('normalized_value', 191);

                $table->boolean('is_primary')->default(false)->index();
                $table->boolean('is_verified')->default(false)->index();
                $table->timestamp('verified_at')->nullable();

                $table->string('visibility', 20)->default('private')->index();
                $table->string('notes', 500)->nullable();

                $table->timestamps();

                $table->index(
                    ['contactable_type', 'contactable_id'],
                    'contact_points_contactable_idx'
                );

                $table->unique(
                    ['contactable_type', 'contactable_id', 'kind', 'normalized_value'],
                    'contact_points_owner_kind_value_unique'
                );
            });
        }

        if (! Schema::hasTable('actor_addresses')) {
            Schema::create('actor_addresses', function (Blueprint $table): void {
                $table->id();

                $table->string('addressable_type', 120);
                $table->unsignedBigInteger('addressable_id');

                $table->string('type', 32)->default('other')->index();
                $table->string('label', 100)->nullable();

                $table->string('country_code', 2)->default('IR');
                $table->string('province', 120)->nullable()->index();
                $table->string('city', 120)->nullable()->index();
                $table->string('district', 160)->nullable();
                $table->string('street', 255)->nullable();
                $table->string('alley', 160)->nullable();
                $table->string('building_no', 60)->nullable();
                $table->string('unit', 60)->nullable();
                $table->string('postal_code', 40)->nullable();

                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();

                $table->boolean('is_primary')->default(false)->index();
                $table->string('visibility', 20)->default('private')->index();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(
                    ['addressable_type', 'addressable_id'],
                    'actor_addresses_addressable_idx'
                );
            });
        }

        if (! Schema::hasTable('business_contacts')) {
            Schema::create('business_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                // The owner can be today's RealEstate intake portal and,
                // after M2, a first-class Business actor without redesign.
                $table->string('owner_type', 120);
                $table->unsignedBigInteger('owner_id');

                $table->string('display_name', 160);
                $table->string('status', 24)->default('active')->index();
                $table->string('source', 80)->nullable()->index();

                // When an anonymous contact later becomes a registered actor/user.
                $table->string('claimed_by_type', 120)->nullable();
                $table->unsignedBigInteger('claimed_by_id')->nullable();
                $table->timestamp('claimed_at')->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(
                    ['owner_type', 'owner_id'],
                    'business_contacts_owner_idx'
                );

                $table->index(
                    ['claimed_by_type', 'claimed_by_id'],
                    'business_contacts_claimed_by_idx'
                );
            });
        }

        if (
            Schema::hasTable('public_real_estate_cases')
            && ! Schema::hasColumn('public_real_estate_cases', 'business_contact_id')
        ) {
            Schema::table('public_real_estate_cases', function (Blueprint $table): void {
                $table->foreignId('business_contact_id')
                    ->nullable()
                    ->after('public_intake_portal_id')
                    ->constrained('business_contacts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('public_real_estate_cases')
            && Schema::hasColumn('public_real_estate_cases', 'business_contact_id')
        ) {
            Schema::table('public_real_estate_cases', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_contact_id');
            });
        }

        Schema::dropIfExists('business_contacts');
        Schema::dropIfExists('actor_addresses');
        Schema::dropIfExists('contact_points');
    }
};
