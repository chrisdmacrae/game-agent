<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presentation metadata for the game grid and hub pages. `accent` is a
     * design-system colour token name (e.g. "teal-400") and `icon` a Lucide
     * icon name; both are rendered by the frontend, never interpolated as CSS.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->string('accent')->default('teal-400')->after('short_name');
            $table->string('icon')->default('swords')->after('accent');
            $table->boolean('is_live')->default(false)->after('icon');
            $table->integer('sort_order')->default(0)->after('is_live');
            $table->string('description')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'accent', 'icon', 'is_live', 'sort_order', 'description']);
        });
    }
};
