<?php

namespace App\Models\Poe2;

class CharacterClass extends Poe2Model
{
    protected $table = 'poe2_classes';

    protected function casts(): array
    {
        return [
            'base_stats' => 'array',
            'raw' => 'array',
        ];
    }
}
