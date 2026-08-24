<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function url(): string
    {
        return route('builds.show', $this->public_id);
    }
}
