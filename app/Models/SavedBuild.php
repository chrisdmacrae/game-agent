<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $game_id
 * @property int|null $game_version_id
 * @property string $public_id
 * @property string $name
 * @property string|null $summary
 * @property string|null $guide_markdown
 * @property array<string, mixed> $build
 * @property array<string, mixed>|null $validation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SavedBuild extends Model
{
    protected $table = 'builds';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'build' => 'array',
            'validation' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SavedBuild $build) {
            $build->public_id ??= Str::lower(Str::random(12));
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

    public function url(): string
    {
        return route('builds.show', $this->public_id);
    }
}
