<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lands the formula-evaluation pass beside the raw text it was derived from.
 *
 * Nothing here replaces an existing column: the tokenised game text stays
 * exactly as the dump wrote it and the evaluated rendering sits next to it, so
 * a reader can always tell what the data said from what we computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('d4_skills', function (Blueprint $table) {
            // Script formula index => formula text. The raw payload deliberately
            // drops ptScriptFormulas, so this is what a later re-evaluation reads.
            $table->jsonb('formulas')->default('{}');
            // Rank => script formula index => value evaluated at that rank.
            $table->jsonb('rank_values')->default('{}');
        });

        Schema::table('d4_affixes', function (Blueprint $table) {
            $table->text('display_text')->nullable();
        });

        Schema::table('d4_aspects', function (Blueprint $table) {
            $table->text('display_text')->nullable();
        });

        Schema::table('d4_uniques', function (Blueprint $table) {
            $table->text('display_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('d4_skills', function (Blueprint $table) {
            $table->dropColumn(['formulas', 'rank_values']);
        });

        Schema::table('d4_affixes', function (Blueprint $table) {
            $table->dropColumn('display_text');
        });

        Schema::table('d4_aspects', function (Blueprint $table) {
            $table->dropColumn('display_text');
        });

        Schema::table('d4_uniques', function (Blueprint $table) {
            $table->dropColumn('display_text');
        });
    }
};
