<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Icon atlas references and request-time calculator tables.
 *
 * `icon` holds the atlas frame an entity's art lives in — texture SNO id,
 * frame index and fractional UV rect, resolved from the Texture group at
 * import. Null means no cloned atlas carries the handle (the UI falls back to
 * a letter badge).
 *
 * `d4_calc_tables` persists the slices of the dump the stat calculator reads
 * at request time (attribute formula graph, weapon damage breakpoints, level
 * scaling, ...), so computing a build's stats never touches the source tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['d4_skills', 'd4_aspects', 'd4_uniques'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->jsonb('icon')->nullable();
            });
        }

        Schema::create('d4_calc_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->jsonb('data')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('d4_calc_tables');

        foreach (['d4_skills', 'd4_aspects', 'd4_uniques'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
