<?php

namespace App\Models\D4;

class Skill extends D4Model
{
    protected $table = 'd4_skills';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'tags' => 'array',
            'enhancements' => 'array',
            'formulas' => 'array',
            'rank_values' => 'array',
            'raw' => 'array',
        ];
    }
}
