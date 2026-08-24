<?php

namespace App\Models\Poe2;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $table = 'poe2_prices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'fetched_at' => 'datetime',
            'value' => 'float',
        ];
    }
}
