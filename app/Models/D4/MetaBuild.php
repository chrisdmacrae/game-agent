<?php

namespace App\Models\D4;

use Illuminate\Database\Eloquent\Model;

/**
 * A single entry of an editorial Diablo IV tier list. Unlike the datamined D4
 * models this is not tied to a game version: meta is season-scoped, the same
 * way poe2_prices is league-scoped.
 */
class MetaBuild extends Model
{
    protected $table = 'd4_meta_builds';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'raw' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
