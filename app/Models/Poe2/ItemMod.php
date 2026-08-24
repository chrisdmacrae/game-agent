<?php

namespace App\Models\Poe2;

class ItemMod extends Poe2Model
{
    protected $table = 'poe2_mods';

    protected function casts(): array
    {
        return [
            'is_essence_only' => 'boolean',
            'groups' => 'array',
            'spawn_tags' => 'array',
            'spawn_weights' => 'array',
            'stats' => 'array',
            'raw' => 'array',
        ];
    }
}
