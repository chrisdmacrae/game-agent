<?php

namespace App\Models\Poe2;

class UniqueItem extends Poe2Model
{
    protected $table = 'poe2_uniques';

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'mods' => 'array',
            'raw' => 'array',
        ];
    }
}
