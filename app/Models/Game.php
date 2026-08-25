<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $short_name
 * @property string $accent design-system colour token name, e.g. "teal-400"
 * @property string $icon Lucide icon name, e.g. "swords"
 * @property bool $is_live
 * @property int $sort_order
 * @property string|null $description
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<GameVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(GameVersion::class);
    }

    /** @return HasMany<Build, $this> */
    public function builds(): HasMany
    {
        return $this->hasMany(Build::class);
    }

    /** @return HasMany<GameVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(GameVote::class);
    }

    public function activeVersion(): ?GameVersion
    {
        return $this->versions()->where('is_active', true)->latest('imported_at')->first();
    }
}
