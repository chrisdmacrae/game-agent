<?php

namespace App\Models;

use App\Domain\Builds\BuildStage;
use Database\Factories\BuildFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A build. The jsonb `build` payload is the source of truth; the columns
 * below it are promoted copies of the fields the hub filters and sorts on,
 * derived by syncPromotedFields() whenever the payload is written.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $game_id
 * @property int|null $game_version_id
 * @property string $public_id
 * @property string $name
 * @property string|null $summary
 * @property string|null $guide_markdown
 * @property string $visibility
 * @property string|null $class
 * @property string|null $ascendancy
 * @property string|null $stage
 * @property string|null $tier
 * @property int|null $level
 * @property int|null $dps
 * @property int|null $ehp
 * @property float|null $cost_divine
 * @property bool|null $hardcore_viable
 * @property int $endorsements_count
 * @property array<string, mixed> $build
 * @property array<string, mixed>|null $validation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Build extends Model
{
    /** @use HasFactory<BuildFactory> */
    use HasFactory;

    public const VISIBILITY_DRAFT = 'draft';

    public const VISIBILITY_PUBLIC = 'public';

    protected $table = 'builds';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'build' => 'array',
            'validation' => 'array',
            'level' => 'integer',
            'dps' => 'integer',
            'ehp' => 'integer',
            // Postgres hands decimals back as strings; the hub sorts and
            // filters on this, so keep it a number on the way out.
            'cost_divine' => 'float',
            'hardcore_viable' => 'boolean',
            'endorsements_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Build $build) {
            $build->public_id ??= Str::lower(Str::random(12));
        });

        // The payload is the source of truth: whenever it is written, the
        // promoted columns are re-derived from it.
        static::saving(function (Build $build) {
            if ($build->isDirty('build')) {
                $build->syncPromotedFields();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<GameVersion, $this> */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /** @return HasMany<Endorsement, $this> */
    public function endorsements(): HasMany
    {
        return $this->hasMany(Endorsement::class);
    }

    /** @return HasMany<BuildBookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(BuildBookmark::class);
    }

    /**
     * @param  EloquentBuilder<$this>  $query
     */
    public function scopePublic(EloquentBuilder $query): void
    {
        $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /**
     * Public builds, plus the viewer's own drafts.
     *
     * @param  EloquentBuilder<$this>  $query
     */
    public function scopeVisibleTo(EloquentBuilder $query, ?Authenticatable $user): void
    {
        $query->where(function (EloquentBuilder $query) use ($user) {
            $query->where('visibility', self::VISIBILITY_PUBLIC);

            if ($user !== null) {
                $query->orWhere('user_id', $user->getAuthIdentifier());
            }
        });
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isDraft(): bool
    {
        return ! $this->isPublic();
    }

    /**
     * Copy the filterable/sortable fields out of the jsonb payload onto real
     * columns. Call this from every path that writes `build`.
     */
    public function syncPromotedFields(): static
    {
        $payload = $this->build ?? [];

        $this->class = $this->stringOrNull($payload['class'] ?? null);
        $this->ascendancy = $this->stringOrNull($payload['ascendancy'] ?? null);
        $this->stage = BuildStage::fromBuild($payload)?->value;
        $this->tier = $this->stringOrNull($payload['tier'] ?? null);
        $this->level = $this->intOrNull($payload['level'] ?? null);
        $this->dps = $this->intOrNull($payload['dps'] ?? null);
        $this->ehp = $this->intOrNull($payload['ehp'] ?? null);
        $this->cost_divine = is_numeric($payload['cost_divine'] ?? null)
            ? (float) $payload['cost_divine']
            : null;
        $this->hardcore_viable = isset($payload['hardcore_viable'])
            ? (bool) $payload['hardcore_viable']
            : null;

        return $this;
    }

    /**
     * The canonical build URL, which is namespaced by the game slug. The old
     * `/builds/{publicId}` URL still resolves and redirects here.
     */
    public function url(): string
    {
        $slug = $this->relationLoaded('game')
            ? $this->game?->slug
            : Game::query()->whereKey($this->game_id)->value('slug');

        return $slug === null
            ? route('builds.show', $this->public_id)
            : route('games.builds.show', [$slug, $this->public_id]);
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
