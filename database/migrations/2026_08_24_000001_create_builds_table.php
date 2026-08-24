<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('summary', 500)->nullable();
            $table->text('guide_markdown')->nullable();
            $table->jsonb('build')->default('{}');
            $table->jsonb('validation')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builds');
    }
};
