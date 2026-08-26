<?php

namespace App\Domain\D4\Calc;

/**
 * Base weapon damage from the data: the item-power breakpoint roll for the
 * weapon's attack-speed class, the item type's share of the damage budget,
 * and its base attacks per second.
 *
 * Two of the inputs are unnamed fields whose meaning was established
 * empirically and stays flagged as an assumption: `damage_multiplier`
 * (unk_b2500f1 — 1.0 for two-hand axes down to 0.375 for daggers and foci)
 * and `damage_spread` (unk_4811bbe — the min-to-max band, uniformly 0.2).
 */
class WeaponModel
{
    public function __construct(protected CalcTables $tables) {}

    /**
     * @param  array<string, mixed>  $item  one payload weapon entry
     * @return array{
     *     average_hit: float,
     *     min: float,
     *     max: float,
     *     attacks_per_second: float,
     *     dps: float,
     *     item_power: int,
     *     speed_class: string,
     *     item_type: string,
     * }|null null when the weapon's item type cannot be resolved
     */
    public function damage(array $item, int $itemPower): ?array
    {
        $type = $this->tables->itemType($item['item_type'] ?? null);

        if ($type === null || ! is_numeric($type['damage_multiplier'] ?? null) || (float) $type['damage_multiplier'] <= 0) {
            return null;
        }

        $attacksPerSecond = $this->baseAttacksPerSecond($type);
        $speedClass = $this->speedClass($attacksPerSecond);
        $roll = $this->tables->weaponDamageRoll($speedClass, $itemPower);

        if ($roll === null) {
            return null;
        }

        $multiplier = (float) $type['damage_multiplier'];
        $spread = is_numeric($type['damage_spread'] ?? null) ? (float) $type['damage_spread'] : 0.2;

        // The breakpoint interval is the roll range across drops; a specific
        // weapon sits somewhere in it. Plan around the midpoint, then apply
        // the min-to-max band a single weapon shows on its tooltip.
        $rollValue = (($roll['min'] + $roll['max']) / 2) * $multiplier;
        $min = $rollValue;
        $max = $rollValue * (1 + $spread);

        return [
            'average_hit' => ($min + $max) / 2,
            'min' => $min,
            'max' => $max,
            'attacks_per_second' => $attacksPerSecond,
            'dps' => (($min + $max) / 2) * $attacksPerSecond,
            'item_power' => $roll['item_power'],
            'speed_class' => $speedClass,
            'item_type' => (string) ($type['name'] ?? ($item['item_type'] ?? '')),
        ];
    }

    /**
     * Base attacks per second from the type's innate speed stats:
     * 1.0 plus Weapon_Speed_Percent_Bonus minus Weapon_Speed_Percent_Reduction
     * reproduces the game's 0.9 / 1.0 / 1.1 / 1.2 tiers exactly.
     *
     * @param  array<string, mixed>  $type
     */
    protected function baseAttacksPerSecond(array $type): float
    {
        $speed = 1.0;

        foreach ($type['innate_stats'] ?? [] as $stat) {
            if (! is_array($stat) || ! is_numeric($stat['value'] ?? null)) {
                continue;
            }

            $attribute = strtolower((string) ($stat['attribute'] ?? ''));

            if (str_contains($attribute, 'speed_percent_bonus')) {
                $speed += (float) $stat['value'];
            } elseif (str_contains($attribute, 'speed_percent_reduction')) {
                $speed -= (float) $stat['value'];
            }
        }

        return round($speed, 3);
    }

    /**
     * The attack-speed class picks which weapon-damage table the roll comes
     * from; slow weapons roll bigger numbers so DPS stays level.
     */
    protected function speedClass(float $attacksPerSecond): string
    {
        return match (true) {
            $attacksPerSecond <= 0.95 => 'slow',
            $attacksPerSecond >= 1.15 => 'very_fast',
            $attacksPerSecond >= 1.05 => 'fast',
            default => 'normal',
        };
    }
}
