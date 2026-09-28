<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_intentions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('ledgers')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('title', 180);
            $table->unsignedBigInteger('amount_minor');
            $table->date('target_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestamps();

            $table->index(['user_id', 'status', 'target_on']);
        });

        Schema::create('personal_secrets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32)->default('login');
            $table->string('title', 180);
            $table->text('identifier')->nullable();
            $table->text('secret')->nullable();
            $table->text('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_secrets');
        Schema::dropIfExists('money_intentions');
    }
};
