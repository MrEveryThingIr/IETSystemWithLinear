<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contexts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('context_uuid_uq');
            $table->string('kind', 32);
            $table->timestamps();

            $table->index('kind', 'context_kind_ix');
        });

        Schema::create('personal_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique('personal_context_context_uq');
            $table->foreignId('actor_id')->unique('personal_context_actor_uq');
            $table->timestamps();

            $table->foreign('context_id', 'personal_context_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('actor_id', 'personal_context_actor_fk')->references('id')->on('actors')->restrictOnDelete();
        });

        Schema::create('group_space_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique('group_space_context_context_uq');
            $table->foreignId('group_space_id')->unique('group_space_context_space_uq');
            $table->timestamps();

            $table->foreign('context_id', 'group_space_context_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('group_space_id', 'group_space_context_space_fk')->references('id')->on('group_spaces')->restrictOnDelete();
        });

        Schema::create('admission_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique('admission_context_context_uq');
            $table->foreignId('admission_id')->unique('admission_context_admission_uq');
            $table->timestamps();

            $table->foreign('context_id', 'admission_context_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('admission_id', 'admission_context_admission_fk')->references('id')->on('admissions')->restrictOnDelete();
        });

        $timestamp = now();

        DB::table('group_spaces')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($spaces) use ($timestamp): void {
                foreach ($spaces as $space) {
                    $contextId = DB::table('contexts')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'kind' => 'group_space',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    DB::table('group_space_contexts')->insert([
                        'context_id' => $contextId,
                        'group_space_id' => $space->id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_contexts');
        Schema::dropIfExists('group_space_contexts');
        Schema::dropIfExists('personal_contexts');
        Schema::dropIfExists('contexts');
    }
};
