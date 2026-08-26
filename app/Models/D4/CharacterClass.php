<?php

namespace App\Models\D4;

class CharacterClass extends D4Model
{
    protected $table = 'd4_classes';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'raw' => 'array',
        ];
    }
}
