<?php

namespace App\Domain\D4\Calc;

use App\Domain\D4\Import\FormulaEvaluator;
use App\Models\D4\Skill;

/**
 * A skill's base hit as a fraction of weapon damage: the primary damage
 * payload's coefficient evaluated at the listed rank (an `SF_n` reference
 * into the skill's stored formulas, already evaluated per rank at import),
 * scaled by the level table's class base-damage scalar.
 */
class SkillDamageModel
{
    public function __construct(
        protected CalcTables $tables,
        protected FormulaEvaluator $evaluator = new FormulaEvaluator,
    ) {}

    /**
     * @return array{coefficient: float, variance: float, rank: int, approximate: bool}|null
     *                                                                                       null when the skill has no evaluable damage payload
     */
    public function baseHit(Skill $skill, int $rank, int $level): ?array
    {
        $damage = $skill->raw['damage'] ?? null;

        if (! is_array($damage) || ! is_string($damage['scalar'] ?? null)) {
            return null;
        }

        $rank = max(1, min($rank, max((int) $skill->max_rank, 1)));
        $approximate = false;
        $coefficient = $this->resolveScalar($skill, (string) $damage['scalar'], $rank, $approximate);

        if ($coefficient === null || $coefficient <= 0) {
            return null;
        }

        $coefficient *= $this->classBaseDamageScalar($damage['class_scalar_index'] ?? null, $level);

        $variance = is_string($damage['variance'] ?? null)
            ? (float) ($this->midpoint($this->evaluator->evaluate($damage['variance'])) ?? 0)
            : 0.0;

        return [
            'coefficient' => $coefficient,
            'variance' => $variance,
            'rank' => $rank,
            'approximate' => $approximate,
        ];
    }

    /**
     * `SF_n` reads the per-rank value the import evaluated; anything else is
     * an expression over the stored formulas. A chain that dead-ends on a
     * formula the import could not evaluate gets one documented fallback: a
     * channeled skill's coefficient is typically `SF_a / SF_b` with SF_b the
     * unevaluable tick interval — the numerator alone IS the per-second
     * coefficient, which is what DPS wants. That path flags the result
     * approximate.
     */
    protected function resolveScalar(Skill $skill, string $scalar, int $rank, bool &$approximate): ?float
    {
        $atRank = $skill->rank_values[(string) $rank] ?? $skill->rank_values[$rank] ?? [];
        $scalar = trim($scalar);

        if (preg_match('/^SF_(\d+)$/', $scalar, $matches) === 1) {
            $direct = $this->midpoint($atRank[$matches[1]] ?? null);

            if ($direct !== null) {
                return $direct;
            }

            // The referenced formula did not evaluate at import; chase its text.
            $scalar = $skill->formulas[$matches[1]] ?? $skill->formulas[(int) $matches[1]] ?? '';

            if (! is_string($scalar) || trim($scalar) === '') {
                return null;
            }
        }

        $variables = [];

        foreach (is_array($atRank) ? $atRank : [] as $index => $value) {
            $variables["SF_{$index}"] = is_array($value) || is_numeric($value) ? $value : 0;
        }

        $evaluated = $this->midpoint($this->evaluator->evaluate($scalar, $variables));

        if ($evaluated !== null) {
            return $evaluated;
        }

        if (preg_match('~^\s*SF_(\d+)\s*/~', $scalar, $matches) === 1) {
            $numerator = $this->midpoint($atRank[$matches[1]] ?? null);

            if ($numerator !== null) {
                $approximate = true;

                return $numerator;
            }
        }

        return null;
    }

    protected function classBaseDamageScalar(mixed $index, int $level): float
    {
        if (! is_numeric($index)) {
            return 1.0;
        }

        $scalars = $this->tables->levelScaling($level)['class_base_damage_scalar'] ?? null;
        $scalar = is_array($scalars) ? ($scalars[(int) $index] ?? null) : null;

        return is_numeric($scalar) && (float) $scalar > 0 ? (float) $scalar : 1.0;
    }

    /**
     * Import-time values are a number, or a {min, max} interval for rolls.
     */
    protected function midpoint(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value) && is_numeric($value['min'] ?? null) && is_numeric($value['max'] ?? null)) {
            return ((float) $value['min'] + (float) $value['max']) / 2;
        }

        return null;
    }
}
