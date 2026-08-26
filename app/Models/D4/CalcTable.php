<?php

namespace App\Models\D4;

/**
 * One named slice of dump data the stat calculator reads at request time —
 * attribute formula graph, weapon damage breakpoints, level scaling and the
 * like — persisted per game version by the importer so the calculator never
 * touches the source tree.
 */
class CalcTable extends D4Model
{
    protected $table = 'd4_calc_tables';

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
