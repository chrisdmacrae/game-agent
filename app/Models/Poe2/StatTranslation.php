<?php

namespace App\Models\Poe2;

class StatTranslation extends Poe2Model
{
    protected $table = 'poe2_stat_translations';

    protected function casts(): array
    {
        return [
            'stat_ids' => 'array',
            'translations' => 'array',
        ];
    }
}
