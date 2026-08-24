<?php

namespace App\Models\Poe2;

class Gem extends Poe2Model
{
    protected $table = 'poe2_gems';

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'tags' => 'array',
            'requirement_weights' => 'array',
            'recommended_supports' => 'array',
            'granted_skills' => 'array',
            'skill_details' => 'array',
            'raw' => 'array',
        ];
    }
}
