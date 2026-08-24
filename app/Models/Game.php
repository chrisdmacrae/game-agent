<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $guarded = [];

    public function versions(): HasMany
    {
        return $this->hasMany(GameVersion::class);
    }

    public function activeVersion(): ?GameVersion
    {
        return $this->versions()->where('is_active', true)->latest('imported_at')->first();
    }
}
