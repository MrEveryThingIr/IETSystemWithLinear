<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('story_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained()->restrictOnDelete();
            $table->string('responsibility');
            $table->timestamps();
            $table->unique(['story_id', 'actor_id', 'responsibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_roles');
        Schema::dropIfExists('stories');
    }
};
