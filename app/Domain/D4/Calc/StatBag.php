<?php

namespace App\Domain\D4\Calc;

/**
 * Bucketed stat accumulation, the way Diablo IV composes damage: most
 * damage% modifiers stack ADDITIVELY inside one bucket, and only crit,
 * vulnerable and explicit multiplicative sources multiply on top. Everything
 * an affix, temper or paragon node contributes lands here keyed by the game's
 * eAttribute name, classified by pattern.
 */
class StatBag
{
    /** Sum of the additive damage% bucket, as a fraction. */
    public float $additiveDamage = 0.0;

    /** Crit chance bonuses, as a fraction on top of the base 5%. */
    public float $critChance = 0.0;

    /** Crit damage bonuses, as a fraction on top of the base 50%. */
    public float $critDamage = 0.0;

    /** Vulnerable damage bonuses, as a fraction on top of the base 20%. */
    public float $vulnerable = 0.0;

    /** Attack speed bonuses, as a fraction. */
    public float $attackSpeed = 0.0;

    /** Flat main core stat from gear and paragon. */
    public float $mainStat = 0.0;

    /** Flat maximum life. */
    public float $flatLife = 0.0;

    /** Maximum life %, as a fraction. */
    public float $lifePercent = 0.0;

    /** Flat armor from gear. */
    public float $armor = 0.0;

    /** Armor %, as a fraction. */
    public float $armorPercent = 0.0;

    /** All-resistance, as a fraction. */
    public float $resistAll = 0.0;

    /** Skill rank bonuses ("+X to <skill>"), counted for the summary only. */
    public float $skillRanks = 0.0;

    /** @var array<string, int> attribute names no bucket claims => count */
    public array $unmapped = [];

    /**
     * Route one attribute contribution into its bucket. Values arrive
     * normalised to the game's internal scale (fractions for percentages).
     */
    public function add(string $attribute, float $value): void
    {
        $name = strtolower($attribute);

        match (true) {
            str_contains($name, 'crit_damage') => $this->critDamage += $value,
            str_contains($name, 'crit_percent') || str_contains($name, 'crit_chance') => $this->critChance += $value,
            str_contains($name, 'vulnerable') => $this->vulnerable += $value,
            str_contains($name, 'attack_speed') || str_contains($name, 'weapon_speed') || str_contains($name, 'attacks_per_second') => $this->attackSpeed += $value,
            str_contains($name, 'damage_percent') || str_starts_with($name, 'damage_bonus') || str_contains($name, 'damage_type_percent') => $this->additiveDamage += $value,
            str_contains($name, 'hitpoints_max_percent') => $this->lifePercent += $value,
            str_contains($name, 'hitpoints') => $this->flatLife += $value,
            str_contains($name, 'armor_percent') => $this->armorPercent += $value,
            str_contains($name, 'armor') => $this->armor += $value,
            str_contains($name, 'resistance') => $this->resistAll += $value,
            str_contains($name, 'strength') || str_contains($name, 'dexterity')
                || str_contains($name, 'intelligence') || str_contains($name, 'willpower')
                || str_contains($name, 'core_stat') => $this->mainStat += $value,
            str_contains($name, 'skill_rank') || str_contains($name, 'talent') => $this->skillRanks += $value,
            default => $this->unmapped[$attribute] = ($this->unmapped[$attribute] ?? 0) + 1,
        };
    }

    /**
     * The full damage multiplier over a skill's base hit: additive bucket,
     * then crit and vulnerable as their own multiplicative buckets.
     */
    public function damageMultiplier(float $baseCritChance, float $baseCritDamage, bool $assumeVulnerable): float
    {
        $critChance = min(1.0, $baseCritChance + $this->critChance);
        $critDamage = $baseCritDamage + $this->critDamage;
        $vulnerable = $assumeVulnerable ? 1.2 + $this->vulnerable : 1.0;

        return (1.0 + $this->additiveDamage)
            * (1.0 + $critChance * $critDamage)
            * $vulnerable;
    }
}
