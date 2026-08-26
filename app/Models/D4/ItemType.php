<?php

namespace App\Models\D4;

class ItemType extends D4Model
{
    protected $table = 'd4_item_types';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'implicits' => 'array',
            'raw' => 'array',
        ];
    }
}
