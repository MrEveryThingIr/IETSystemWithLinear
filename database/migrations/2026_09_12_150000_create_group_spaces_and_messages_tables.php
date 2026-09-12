<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_spaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('kind', 40)->default('chat');
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'slug']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('group_space_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_space_id')->constrained('group_spaces')->cascadeOnDelete();
            $table->foreignId('author_actor_id')->constrained('actors')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['group_space_id', 'id']);
        });

        DB::table('groups')
            ->select(['id', 'created_by_actor_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($groups): void {
                $rows = $groups->map(fn ($group): array => [
                    'group_id' => $group->id,
                    'created_by_actor_id' => $group->created_by_actor_id,
                    'name' => 'General',
                    'slug' => 'general',
                    'kind' => 'chat',
                    'status' => 'active',
                    'is_default' => true,
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                ])->all();

                if ($rows !== []) {
                    DB::table('group_spaces')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_space_messages');
        Schema::dropIfExists('group_spaces');
    }
};
