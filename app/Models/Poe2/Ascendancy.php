<?php

namespace App\Models\Poe2;

class Ascendancy extends Poe2Model
{
    protected $table = 'poe2_ascendancies';

    protected function casts(): array
    {
        return [
            'raw' => 'array',
        ];
    }
}
