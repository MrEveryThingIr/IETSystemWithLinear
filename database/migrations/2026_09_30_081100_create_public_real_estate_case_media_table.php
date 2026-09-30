<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public_real_estate_case_media')) {
            return;
        }

        Schema::create('public_real_estate_case_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_real_estate_case_id')
                ->constrained('public_real_estate_cases')
                ->cascadeOnDelete();
            $table->string('kind', 20)->index();       // image | video | audio
            $table->string('origin', 20)->default('upload'); // upload | recorded
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['public_real_estate_case_id', 'kind', 'sort_order'],
                'public_re_case_media_case_kind_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_real_estate_case_media');
    }
};
