<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_surface_grants')) {
            Schema::create('feature_surface_grants', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('surface_key', 120)->index();
                $t->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('granted_at')->nullable();
                $t->timestamps();
                $t->unique(['user_id', 'surface_key']);
            });
        }
        if (! Schema::hasTable('feature_surface_settings')) {
            Schema::create('feature_surface_settings', function (Blueprint $t): void {
                $t->id();
                $t->string('key', 120)->unique();
                $t->text('value')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('feature_surface_events')) {
            Schema::create('feature_surface_events', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('surface_key', 120)->nullable()->index();
                $t->string('event', 40)->index();
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_surface_events');
        Schema::dropIfExists('feature_surface_settings');
        Schema::dropIfExists('feature_surface_grants');
    }
};
