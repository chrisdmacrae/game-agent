<?php

namespace App\Domain\D4\Calc;

/**
 * The hand-maintained constants the calculator needs that the d4data dump
 * never contains, because the server sets them at runtime: the class base
 * life curve, the flat-life level scaling and the core-stat benefit rates.
 *
 * Every constant here is an ASSUMPTION until it has been verified in game,
 * and every use of one is reported in the computed stats' `assumptions` list
 * so published numbers stay honest about their basis. Verify with the
 * checklist below and update the values (and their `source` notes) in place.
 *
 * In-game verification checklist (run once per patch that touches these):
 *  1. Base life: note the sheet Life of a naked character at levels 1, 25,
 *     60 and 70 per class; fit BASE_LIFE / LIFE_PER_LEVEL.
 *  2. Flat life: equip one "+X Maximum Life" affix and note the sheet delta
 *     at two levels; calibrate FLAT_LIFE_LEVEL_SCALAR (the engine's
 *     TableHealthFromFlat curve).
 *  3. Core stats: note the armor / damage% sheet deltas from a single
 *     +core-stat affix; calibrate the CORE_STAT_* rates.
 *  4. Weapon spread: compare a weapon tooltip's min-max band to the
 *     WeaponModel's 0.2 spread assumption.
 */
class Calibration
{
    /**
     * Sheet life of a naked level-1 character. The game has used a shared
     * base across classes since 2.0.
     *
     * Source: community sheets (uncalibrated for the Lord of Hatred ladder —
     * verify in game, checklist step 1).
     */
    public const BASE_LIFE = 40.0;

    /**
     * Life gained per character level, applied linearly. The real curve is
     * mildly super-linear; this is the placeholder pending checklist step 1.
     */
    public const LIFE_PER_LEVEL = 62.0;

    /**
     * The engine's TableHealthFromFlat(flat, level) curve, approximated as a
     * flat multiplier: "+X Maximum Life" on gear counts as X * this.
     * Pending checklist step 2.
     */
    public const FLAT_LIFE_LEVEL_SCALAR = 1.0;

    /**
     * Damage% per point of the class main core stat, as a fraction
     * (0.1% per point). Source: in-game stat sheet convention since release.
     */
    public const MAIN_STAT_DAMAGE_PER_POINT = 0.001;

    /**
     * Armor per point of Strength (`Core_Stat_Minor_Benefit_Scalar_Strength`
     * defaults to 0 in the dump; the live rate is 1). Source: in-game sheet.
     */
    public const ARMOR_PER_STRENGTH = 1.0;

    /**
     * All-resistance per point of Intelligence, as a fraction
     * (`Resistance_All_Core_Stat_Bonus`, live rate 0.05% per point).
     */
    public const RESIST_ALL_PER_INTELLIGENCE = 0.0005;

    /**
     * Base critical strike chance, as a fraction. Source: in-game sheet.
     */
    public const BASE_CRIT_CHANCE = 0.05;

    /**
     * Main stat granted by one allocated paragon core-stat node. Normal
     * nodes grant 5; the calculator counts allocated normal nodes whose
     * attribute matches the class main stat.
     */
    public const PARAGON_NODE_MAIN_STAT = 5.0;

    /**
     * The base life a character actually has at a level, before gear.
     */
    public static function baseLife(int $level): float
    {
        return self::BASE_LIFE + self::LIFE_PER_LEVEL * max(0, $level - 1);
    }
}
