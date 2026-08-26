<?php

namespace App\Models\D4;

class ParagonGlyph extends D4Model
{
    protected $table = 'd4_paragon_glyphs';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'effects' => 'array',
            'raw' => 'array',
        ];
    }
}
