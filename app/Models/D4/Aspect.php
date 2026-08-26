<?php

namespace App\Models\D4;

class Aspect extends D4Model
{
    protected $table = 'd4_aspects';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'item_types' => 'array',
            'value_range' => 'array',
            'raw' => 'array',
        ];
    }
}
