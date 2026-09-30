<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_intake_portals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_token', 48)->unique();
            $table->string('type', 50)->default('real_estate')->index();
            $table->string('title', 160);
            $table->string('welcome_heading', 200)->nullable();
            $table->text('welcome_body')->nullable();
            $table->text('success_message')->nullable();
            $table->string('locale', 10)->default('fa');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('public_real_estate_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_intake_portal_id')
                ->constrained('public_intake_portals')
                ->cascadeOnDelete();

            $table->string('reference_code', 16)->unique();
            $table->string('intent', 20)->index(); // offer | need
            $table->string('transaction_mode', 20)->index(); // sale | rent

            $table->string('contact_name', 120);
            $table->string('phone', 32);
            $table->text('exact_address')->nullable();
            $table->string('public_area', 180)->nullable();

            $table->string('property_class', 40)->index();
            $table->string('property_subtype', 80)->nullable();
            $table->decimal('land_area', 12, 2)->nullable();
            $table->decimal('construction_area', 12, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->unsignedTinyInteger('frontage_count')->nullable();

            $table->unsignedSmallInteger('built_year')->nullable();
            $table->string('built_year_calendar', 12)->default('jalali');
            $table->string('building_condition', 40)->nullable();

            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->string('bedrooms_note', 255)->nullable();

            $table->string('cabinet_type', 40)->nullable();
            $table->boolean('has_false_ceiling')->nullable();
            $table->string('false_ceiling_note', 255)->nullable();
            $table->string('heating_system', 60)->nullable();
            $table->string('cooling_system', 60)->nullable();
            $table->string('yard_finish', 60)->nullable();
            $table->string('flooring_type', 60)->nullable();
            $table->string('flooring_note', 255)->nullable();

            $table->boolean('has_parking')->nullable();
            $table->string('parking_type', 40)->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->unsignedSmallInteger('car_capacity')->nullable();
            $table->unsignedSmallInteger('motorbike_capacity')->nullable();
            $table->string('parking_note', 255)->nullable();

            $table->string('roof_finish', 60)->nullable();
            $table->string('roof_note', 255)->nullable();
            $table->boolean('roof_has_parapet')->nullable();

            $table->boolean('has_western_toilet')->nullable();
            $table->boolean('has_iranian_toilet')->nullable();

            $table->decimal('asking_price', 20, 0)->nullable();
            $table->decimal('deposit_amount', 20, 0)->nullable();
            $table->decimal('monthly_rent_amount', 20, 0)->nullable();
            $table->string('price_unit', 16)->default('toman');

            $table->text('notes')->nullable();

            $table->string('status', 24)->default('new')->index();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->char('preview_token_hash', 64);
            $table->timestamp('preview_expires_at')->nullable();
            $table->timestamp('preview_viewed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['public_intake_portal_id', 'status', 'transaction_mode'],
                'public_real_estate_cases_portal_status_tx_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_real_estate_cases');
        Schema::dropIfExists('public_intake_portals');
    }
};
