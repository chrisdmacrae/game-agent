<?php

namespace App\Domain\D4\Calc;

/**
 * Armor damage reduction and effective HP from the level table's damping
 * curves: `DR = scalar * armor / (armor + damping)`, the diminishing-returns
 * shape the LevelScaling sheet parameterises per level.
 */
class MitigationModel
{
    public function __construct(protected CalcTables $tables) {}

    /**
     * Physical damage reduction from armor at a level, as a fraction.
     */
    public function armorReduction(float $armor, int $level): float
    {
        $scaling = $this->tables->levelScaling($level) ?? [];
        $damping = is_numeric($scaling['armor_damping'] ?? null) ? (float) $scaling['armor_damping'] : null;
        $scalar = is_numeric($scaling['armor_dr_scalar'] ?? null) ? (float) $scaling['armor_dr_scalar'] : 0.9;

        if ($damping === null || $damping <= 0 || $armor <= 0) {
            return 0.0;
        }

        return min(0.85, $scalar * $armor / ($armor + $damping));
    }

    /**
     * Effective HP against physical hits: life inflated by armor mitigation.
     */
    public function effectiveHp(float $life, float $armor, int $level): float
    {
        $reduction = $this->armorReduction($armor, $level);

        return $reduction >= 1.0 ? $life : $life / (1.0 - $reduction);
    }
}
