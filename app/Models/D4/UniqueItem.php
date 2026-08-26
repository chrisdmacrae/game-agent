<?php

namespace App\Models\D4;

class UniqueItem extends D4Model
{
    protected $table = 'd4_uniques';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'is_mythic' => 'boolean',
            'affixes' => 'array',
            'icon' => 'array',
            'raw' => 'array',
        ];
    }
}
