<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Save" action on a build page. Named build_bookmarks rather than
     * saved_builds because "saved build" already means a row in `builds`.
     */
    public function up(): void
    {
        Schema::create('build_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('build_id')->constrained('builds')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'build_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_bookmarks');
    }
};
