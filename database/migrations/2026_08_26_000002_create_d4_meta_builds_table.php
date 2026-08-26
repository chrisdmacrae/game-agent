<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editorial "meta" for Diablo IV: there is no telemetry-based meta and no
     * economy data for D4, so the only signal available is a published tier
     * list. Rows are season-scoped rather than game_version_id-scoped — the
     * same precedent poe2_prices sets for league-scoped economy data — because
     * a tier list tracks the live season, not the imported client patch.
     */
    public function up(): void
    {
        Schema::create('d4_meta_builds', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('maxroll');
            $table->string('season')->nullable();
            $table->string('name');
            $table->string('class_name')->nullable();
            $table->string('tier'); // Verbatim from the source: S/A/B/C/D plus X for bugged builds.
            $table->jsonb('tags')->default('[]');
            $table->string('guide_url')->nullable();
            $table->jsonb('raw')->default('{}');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['source', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('d4_meta_builds');
    }
};
