<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('global_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('global_role_permissions', function (Blueprint $table): void {
            $table->foreignId('global_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['global_role_id', 'global_permission_id']);
        });

        Schema::create('global_user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'global_role_id']);
        });

        Schema::create('global_user_permissions', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'global_permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_user_permissions');
        Schema::dropIfExists('global_user_roles');
        Schema::dropIfExists('global_role_permissions');
        Schema::dropIfExists('global_permissions');
        Schema::dropIfExists('global_roles');
    }
};
