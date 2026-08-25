<?php

namespace App\Domain\Builds;

use App\Domain\Poe2\Validation\BuildValidator;
use App\Models\Build;

/**
 * The pre-flight a build must pass before it can be published (scope §3.8).
 * A draft may be as partial as the assistant left it; publishing is the point
 * where the numbers have to be there.
 *
 * Used by the save_build MCP tool and by the web publish flow.
 */
class PublishChecklist
{
    /**
     * @return list<array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function for(Build $build): array
    {
        $payload = $build->build ?? [];

        return [
            $this->statsCheck($payload),
            $this->gearCheck($payload),
            $this->passiveBudgetCheck($payload),
            $this->patchCheck($build),
        ];
    }

    public function passes(Build $build): bool
    {
        return $this->failures($build) === [];
    }

    /**
     * @return list<array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function failures(Build $build): array
    {
        return array_values(array_filter($this->for($build), fn (array $check) => ! $check['passed']));
    }

    /**
     * DPS and EHP, or the offence/defence stat rows the overview tab renders.
     *
     * @param  array<string, mixed>  $payload
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function statsCheck(array $payload): array
    {
        $hasHeadline = isset($payload['dps'], $payload['ehp']);
        $hasRows = ($payload['stats']['offence'] ?? []) !== [] && ($payload['stats']['defence'] ?? []) !== [];

        return $this->check(
            'stats',
            'Stats present',
            $hasHeadline || $hasRows,
            'Add dps and ehp, or offence and defence rows under stats.',
        );
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

        return $this->check(
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
            return $this->check(
                'passives',
                'Passive budget',
                false,
                'Set the character level and the passives taken so the point budget can be checked.',
            );
        }

        $budget = BuildValidator::passivePointBudget((int) $level);
        $passed = (int) $pointsUsed <= $budget;

        return $this->check(
            'passives',
            'Passive budget',
            $passed,
            $passed ? null : "Uses {$pointsUsed} passive points; a level {$level} character has about {$budget}.",
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function patchCheck(Build $build): array
    {
        $activeVersionId = $build->game?->activeVersion()?->id;

        if ($activeVersionId === null) {
            return $this->check('patch', 'Patch current', true, null);
        }

        $passed = $build->game_version_id === $activeVersionId;

        return $this->check(
            'patch',
            'Patch current',
            $passed,
            $passed ? null : 'The build was saved against an older patch; re-save it on the current one.',
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function check(string $key, string $label, bool $passed, ?string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'detail' => $passed ? null : $detail,
        ];
    }
}
