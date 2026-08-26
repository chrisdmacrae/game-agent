<?php

namespace App\Models\D4;

use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class D4Model extends Model
{
    protected $guarded = [];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function scopeForVersion(Builder $query, int $gameVersionId): Builder
    {
        return $query->where('game_version_id', $gameVersionId);
    }

    /**
     * Unreleased/PTR content is imported but hidden by default; this scope is
     * how callers opt back into live-only rows.
     */
    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('is_released', true);
    }
}
