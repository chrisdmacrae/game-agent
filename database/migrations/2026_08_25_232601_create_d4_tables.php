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

        Schema::create('d4_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('resource')->nullable(); // fury|essence|spirit|mana|...
            $table->text('description')->nullable();
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
        });

        Schema::create('d4_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('class_name')->nullable();
            $table->string('category')->nullable(); // basic|core|defensive|ultimate|...
            $table->unsignedInteger('max_rank')->default(0);
            $table->text('description')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->jsonb('enhancements')->default('[]');
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
            $table->index(['game_version_id', 'class_name']);
        });

        Schema::create('d4_paragon_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('class_name')->nullable();
            $table->jsonb('grid')->default('[]');
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
        });

        Schema::create('d4_paragon_glyphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('class_name')->nullable();
            $table->jsonb('effects')->default('[]');
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
        });

        Schema::create('d4_affixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 512);
            $table->text('name')->nullable();
            $table->string('magic_type')->nullable();
            $table->text('text')->nullable();
            $table->jsonb('item_types')->default('[]');
            $table->string('class_name')->nullable();
            $table->boolean('is_tempering')->default(false);
            $table->string('temper_family')->nullable();
            $table->jsonb('value_range')->default('{}');
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'key']);
            $table->index(['game_version_id', 'is_tempering']);
        });

        Schema::create('d4_aspects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('category')->nullable(); // offensive|defensive|mobility|...
            $table->text('text')->nullable();
            $table->jsonb('item_types')->default('[]');
            $table->jsonb('value_range')->default('{}');
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
        });

        Schema::create('d4_uniques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('item_type')->nullable();
            $table->string('class_name')->nullable();
            $table->boolean('is_mythic')->default(false);
            $table->jsonb('affixes')->default('[]');
            $table->text('power_text')->nullable();
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
        });

        Schema::create('d4_item_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sno_id');
            $table->string('name');
            $table->string('slot')->nullable();
            $table->jsonb('implicits')->default('[]');
            $table->boolean('is_released')->default(true);
            $table->jsonb('raw')->default('{}');
            $table->timestamps();

            $table->unique(['game_version_id', 'sno_id']);
        });

        if ($isPostgres) {
            DB::statement('CREATE INDEX d4_skills_name_trgm ON d4_skills USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX d4_paragon_boards_name_trgm ON d4_paragon_boards USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX d4_paragon_glyphs_name_trgm ON d4_paragon_glyphs USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX d4_affixes_text_trgm ON d4_affixes USING gin (text gin_trgm_ops)');
            DB::statement('CREATE INDEX d4_aspects_name_trgm ON d4_aspects USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX d4_uniques_name_trgm ON d4_uniques USING gin (name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('d4_item_types');
        Schema::dropIfExists('d4_uniques');
        Schema::dropIfExists('d4_aspects');
        Schema::dropIfExists('d4_affixes');
        Schema::dropIfExists('d4_paragon_glyphs');
        Schema::dropIfExists('d4_paragon_boards');
        Schema::dropIfExists('d4_skills');
        Schema::dropIfExists('d4_classes');
    }
};
