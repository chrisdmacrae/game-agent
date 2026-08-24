<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('game_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('league')->nullable();
            $table->string('fingerprint')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_versions');
        Schema::dropIfExists('games');
    }
};
