<?php

namespace App\Models\D4;

class Affix extends D4Model
{
    protected $table = 'd4_affixes';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'is_tempering' => 'boolean',
            'item_types' => 'array',
            'value_range' => 'array',
            'raw' => 'array',
        ];
    }
}
