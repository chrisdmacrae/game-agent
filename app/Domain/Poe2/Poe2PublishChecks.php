<?php

namespace App\Domain\Poe2;

use App\Domain\Builds\PublishChecklist;
use App\Domain\Poe2\Validation\BuildValidator;

/**
 * The PoE 2 half of the publish pre-flight: a body armour and a weapon on the
 * gear list, and a passive allocation that fits the character's level.
 *
 * PublishChecklist owns the game-agnostic checks around these and the shape
 * every check returns; this class only decides what "finished" means for a
 * Path of Exile 2 build.
 */
class Poe2PublishChecks
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function checks(array $payload): array
    {
        return [
            $this->gearCheck($payload),
            $this->passiveBudgetCheck($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function gearCheck(array $payload): array
    {
        $slots = array_column($payload['gear'] ?? [], 'slot');

        $hasBody = in_array('body', $slots, true);
        $hasWeapon = in_array('weapon1', $slots, true) || in_array('weapon2', $slots, true);

        $missing = array_values(array_filter([
            $hasBody ? null : 'body armour',
            $hasWeapon ? null : 'weapon',
        ]));

        return PublishChecklist::check(
            'gear',
            'Gear list complete',
            $missing === [],
            $missing === [] ? null : 'Missing gear for: '.implode(', ', $missing).'.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function passiveBudgetCheck(array $payload): array
    {
        $level = $payload['level'] ?? null;
        $nodeIds = $payload['passives']['node_ids'] ?? [];
        $pointsUsed = $payload['passives']['points_used'] ?? ($nodeIds !== [] ? count($nodeIds) : null);

        if (! is_numeric($level) || ! is_numeric($pointsUsed)) {
            return PublishChecklist::check(
                'passives',
                'Passive budget',
                false,
                'Set the character level and the passives taken so the point budget can be checked.',
            );
        }

        $budget = BuildValidator::passivePointBudget((int) $level);
        $passed = (int) $pointsUsed <= $budget;

        return PublishChecklist::check(
            'passives',
            'Passive budget',
            $passed,
            $passed ? null : "Uses {$pointsUsed} passive points; a level {$level} character has about {$budget}.",
        );
    }
}
