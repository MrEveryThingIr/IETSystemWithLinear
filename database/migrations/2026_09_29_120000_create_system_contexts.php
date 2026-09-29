<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique()
                ->constrained('contexts')->restrictOnDelete();
            $table->string('key', 80)->unique();
            $table->string('name', 180);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_contexts');
    }
};
