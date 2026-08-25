<?php

use App\Domain\Builds\BuildStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The jsonb `build` payload stays the source of truth. These columns are
     * promoted copies of the handful of fields the hub filters and sorts on,
     * kept in sync by Build::syncPromotedFields().
     *
     * Existing rows predate drafts, so they are backfilled to 'public' to
     * preserve today's behaviour: everything already saved is already listed.
     */
    public function up(): void
    {
        Schema::table('builds', function (Blueprint $table) {
            $table->string('visibility')->default('draft')->after('guide_markdown');
            $table->string('class')->nullable()->after('visibility');
            $table->string('ascendancy')->nullable()->after('class');
            $table->string('stage')->nullable()->after('ascendancy');
            $table->string('tier')->nullable()->after('stage');
            $table->integer('level')->nullable()->after('tier');
            $table->bigInteger('dps')->nullable()->after('level');
            $table->bigInteger('ehp')->nullable()->after('dps');
            $table->decimal('cost_divine', 10, 2)->nullable()->after('ehp');
            $table->boolean('hardcore_viable')->nullable()->after('cost_divine');
            $table->integer('endorsements_count')->default(0)->after('hardcore_viable');

            $table->index(['game_id', 'visibility']);
            $table->index('class');
            $table->index('stage');
        });

        DB::table('builds')->update(['visibility' => 'public']);

        $this->backfillPromotedColumns();
    }

    public function down(): void
    {
        Schema::table('builds', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'visibility']);
            $table->dropIndex(['class']);
            $table->dropIndex(['stage']);

            $table->dropColumn([
                'visibility', 'class', 'ascendancy', 'stage', 'tier', 'level',
                'dps', 'ehp', 'cost_divine', 'hardcore_viable', 'endorsements_count',
            ]);
        });
    }

    /**
     * Read each payload in PHP rather than with jsonb operators so the
     * backfill runs on both Postgres and the sqlite test database.
     */
    protected function backfillPromotedColumns(): void
    {
        DB::table('builds')->orderBy('id')->select('id', 'build')->chunkById(200, function ($builds) {
            foreach ($builds as $row) {
                $payload = json_decode((string) ($row->build ?? '{}'), true);

                if (! is_array($payload)) {
                    continue;
                }

                DB::table('builds')->where('id', $row->id)->update([
                    'class' => is_string($payload['class'] ?? null) ? $payload['class'] : null,
                    'ascendancy' => is_string($payload['ascendancy'] ?? null) ? $payload['ascendancy'] : null,
                    'level' => is_numeric($payload['level'] ?? null) ? (int) $payload['level'] : null,
                    'stage' => BuildStage::fromBuild($payload)?->value,
                ]);
            }
        });
    }
};
