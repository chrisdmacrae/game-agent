<?php

namespace App\Domain\D4\Calc;

use App\Models\D4\CalcTable;

/**
 * Typed access to the d4_calc_tables rows the importer persisted — the slices
 * of the dump the calculator reads at request time, so computing a build's
 * stats never touches the source tree.
 */
class CalcTables
{
    /** @var array<string, array<array-key, mixed>> */
    protected array $tables = [];

    public function __construct(protected int $versionId) {}

    /**
     * @return array<array-key, mixed>
     */
    public function table(string $key): array
    {
        if (! array_key_exists($key, $this->tables)) {
            $data = CalcTable::forVersion($this->versionId)->where('key', $key)->value('data');

            $this->tables[$key] = is_array($data) ? $data : [];
        }

        return $this->tables[$key];
    }

    /**
     * The level-scaling row for a character level (the highest row at or
     * below it).
     *
     * @return array<string, mixed>|null
     */
    public function levelScaling(int $level): ?array
    {
        $best = null;

        foreach ($this->table('level_scaling') as $row) {
            if (is_array($row) && (int) ($row['level'] ?? 0) <= $level
                && ($best === null || (int) $row['level'] > (int) $best['level'])) {
                $best = $row;
            }
        }

        return $best;
    }

    /**
     * The evaluated weapon-damage roll for an attack-speed class at an item
     * power: the highest breakpoint at or below it.
     *
     * @return array{min: float, max: float, item_power: int}|null
     */
    public function weaponDamageRoll(string $speedClass, int $itemPower): ?array
    {
        $best = null;

        foreach ($this->table('weapon_damage_breakpoints')[$speedClass] ?? [] as $range) {
            if (! is_array($range) || ! is_numeric($range['min'] ?? null) || ! is_numeric($range['max'] ?? null)) {
                continue;
            }

            if ((int) ($range['item_power'] ?? 0) <= $itemPower
                && ($best === null || (int) $range['item_power'] > (int) $best['item_power'])) {
                $best = $range;
            }
        }

        return $best === null ? null : [
            'min' => (float) $best['min'],
            'max' => (float) $best['max'],
            'item_power' => (int) $best['item_power'],
        ];
    }

    /**
     * The per-item-type constants for a payload `item_type` string, matched
     * against the imported key or display name, case-insensitively.
     *
     * @return array<string, mixed>|null
     */
    public function itemType(?string $itemType): ?array
    {
        if (! is_string($itemType) || trim($itemType) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($itemType));

        foreach ($this->table('item_types') as $key => $type) {
            if (! is_array($type)) {
                continue;
            }

            if (mb_strtolower((string) $key) === $needle
                || mb_strtolower((string) ($type['name'] ?? '')) === $needle) {
                return $type;
            }
        }

        return null;
    }

    public function global(string $field): mixed
    {
        return $this->table('globals')[$field] ?? null;
    }
}
