<?php

namespace App\Models\Poe2;

class PassiveNode extends Poe2Model
{
    protected $table = 'poe2_passive_nodes';

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'connections' => 'array',
            'raw' => 'array',
        ];
    }
}
