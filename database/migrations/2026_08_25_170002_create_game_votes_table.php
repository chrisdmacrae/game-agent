<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waitlist votes for games that are not live yet. Votes are by email, not
     * by account: voting must work without signing up.
     */
    public function up(): void
    {
        Schema::create('game_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->boolean('notify_on_launch')->default(true);
            $table->timestamps();

            $table->unique(['game_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_votes');
    }
};
