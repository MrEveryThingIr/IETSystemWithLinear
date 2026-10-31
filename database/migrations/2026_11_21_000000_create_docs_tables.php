<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public documentation is repository-owned files, so it needs no table of its own. These two tables only serve it:
 * invitation links for the invite-only mode, and the proposals experts send in. Neither touches Content, profiles or
 * any other part of the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('label', 120);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('doc_contributions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 10);
            $table->string('topic', 80);
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('field', 80);
            $table->text('expertise');
            $table->text('education');
            $table->text('experience')->nullable();
            $table->text('links')->nullable();
            $table->text('proposal');
            $table->boolean('may_credit')->default(false);
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('topic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_contributions');
        Schema::dropIfExists('doc_invitations');
    }
};
