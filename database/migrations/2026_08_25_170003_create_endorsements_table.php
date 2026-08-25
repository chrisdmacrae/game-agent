<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One endorsement per user per build; the count is denormalised onto
     * builds.endorsements_count for sorting.
     */
    public function up(): void
    {
        Schema::create('endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('build_id')->constrained('builds')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'build_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endorsements');
    }
};
