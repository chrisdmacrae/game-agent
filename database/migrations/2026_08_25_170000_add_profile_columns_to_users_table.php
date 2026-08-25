<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public profile fields. `handle` is the display identity on build pages;
     * it is backfilled from the existing name so no account is left without one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handle')->nullable()->unique()->after('name');
            $table->string('discord_username')->nullable()->after('handle');
            $table->text('bio')->nullable()->after('discord_username');
        });

        $this->backfillHandles();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handle']);
            $table->dropColumn(['handle', 'discord_username', 'bio']);
        });
    }

    /**
     * Slugify each name and de-duplicate with a numeric suffix.
     */
    protected function backfillHandles(): void
    {
        $taken = [];

        DB::table('users')->orderBy('id')->select('id', 'name')->chunkById(200, function ($users) use (&$taken) {
            foreach ($users as $user) {
                $base = Str::slug((string) $user->name) ?: 'player';
                $handle = $base;
                $suffix = 1;

                while (isset($taken[$handle])) {
                    $handle = $base.'-'.(++$suffix);
                }

                $taken[$handle] = true;

                DB::table('users')->where('id', $user->id)->update(['handle' => $handle]);
            }
        });
    }
};
