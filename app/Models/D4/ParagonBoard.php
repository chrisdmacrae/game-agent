<?php

namespace App\Models\D4;

class ParagonBoard extends D4Model
{
    protected $table = 'd4_paragon_boards';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'grid' => 'array',
            'raw' => 'array',
        ];
    }
}
