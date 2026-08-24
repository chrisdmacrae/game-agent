<?php

namespace App\Models\Poe2;

use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class Poe2Model extends Model
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
}
