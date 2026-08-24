<?php

namespace App\Models\Poe2;

class ItemBase extends Poe2Model
{
    protected $table = 'poe2_item_bases';

    protected function casts(): array
    {
        return [
            'implicits' => 'array',
            'requirements' => 'array',
            'tags' => 'array',
            'properties' => 'array',
            'raw' => 'array',
        ];
    }
}
