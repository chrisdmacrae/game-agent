<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPostgres = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($isPostgres) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        Schema::create('poe2_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('metadata_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('base_stats')->default('{}');
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'metadata_id']);
        });

        Schema::create('poe2_ascendancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('class_name')->nullable();
            $table->text('flavour_text')->nullable();
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'key']);
        });

        Schema::create('poe2_gems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('metadata_id');
            $table->string('name');
            $table->string('gem_type');
            $table->string('color', 8)->nullable();
            $table->boolean('is_released')->default(true);
            $table->text('description')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->jsonb('requirement_weights')->default('{}');
            $table->jsonb('recommended_supports')->default('[]');
            $table->jsonb('granted_skills')->default('[]');
            $table->jsonb('skill_details')->default('[]');
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'metadata_id']);
            $table->index(['game_version_id', 'gem_type']);
        });

        Schema::create('poe2_passive_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('node_id');
            $table->string('name')->nullable();
            $table->string('kind', 32)->default('small'); // small|notable|keystone|jewel_socket|class_start|ascendancy
            $table->string('ascendancy_key')->nullable();
            $table->jsonb('stats')->default('[]');
            $table->jsonb('connections')->default('[]');
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'node_id']);
            $table->index(['game_version_id', 'kind']);
        });

        Schema::create('poe2_item_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('metadata_id');
            $table->string('name');
            $table->string('item_class');
            $table->string('domain')->nullable();
            $table->unsignedInteger('drop_level')->default(0);
            $table->string('release_state')->nullable();
            $table->jsonb('implicits')->default('[]');
            $table->jsonb('requirements')->default('{}');
            $table->jsonb('tags')->default('[]');
            $table->jsonb('properties')->default('{}');
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'metadata_id']);
            $table->index(['game_version_id', 'item_class']);
        });

        Schema::create('poe2_mods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 512);
            $table->text('name')->nullable();
            $table->string('domain');
            $table->string('generation_type');
            $table->string('group_type', 512)->nullable();
            $table->unsignedInteger('required_level')->default(0);
            $table->boolean('is_essence_only')->default(false);
            $table->text('text')->nullable();
            $table->jsonb('groups')->default('[]');
            $table->jsonb('spawn_tags')->default('[]'); // tags with weight > 0, for filtering
            $table->jsonb('spawn_weights')->default('[]');
            $table->jsonb('stats')->default('[]');
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'key']);
            $table->index(['game_version_id', 'domain', 'generation_type']);
        });

        Schema::create('poe2_uniques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('base_name');
            $table->string('item_class')->nullable();
            $table->unsignedInteger('implicit_count')->default(0);
            $table->jsonb('variants')->default('[]');
            $table->jsonb('mods')->default('[]'); // [{text, tags[], variants[], is_implicit}]
            $table->text('source_text')->nullable();
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'name']);
        });

        Schema::create('poe2_stat_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('primary_stat_id', 512);
            $table->jsonb('stat_ids')->default('[]');
            $table->jsonb('translations')->default('[]');
            $table->timestamps();

            $table->index(['game_version_id', 'primary_stat_id']);
        });

        Schema::create('poe2_prices', function (Blueprint $table) {
            $table->id();
            $table->string('league');
            $table->string('category');
            $table->string('name');
            $table->decimal('value', 16, 4)->nullable();
            $table->string('currency', 32)->default('exalted');
            $table->jsonb('raw')->default('{}');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['league', 'category', 'name']);
        });

        if ($isPostgres) {
            DB::statement('CREATE INDEX poe2_gems_name_trgm ON poe2_gems USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX poe2_uniques_name_trgm ON poe2_uniques USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX poe2_mods_text_trgm ON poe2_mods USING gin (text gin_trgm_ops)');
            DB::statement('CREATE INDEX poe2_passive_nodes_name_trgm ON poe2_passive_nodes USING gin (name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('poe2_prices');
        Schema::dropIfExists('poe2_stat_translations');
        Schema::dropIfExists('poe2_uniques');
        Schema::dropIfExists('poe2_mods');
        Schema::dropIfExists('poe2_item_bases');
        Schema::dropIfExists('poe2_passive_nodes');
        Schema::dropIfExists('poe2_gems');
        Schema::dropIfExists('poe2_ascendancies');
        Schema::dropIfExists('poe2_classes');
    }
};
