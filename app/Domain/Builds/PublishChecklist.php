<?php

namespace App\Domain\Builds;

use App\Models\Build;

/**
 * The pre-flight a build must pass before it can be published (scope §3.8).
 * A draft may be as partial as the assistant left it; publishing is the point
 * where the numbers have to be there.
 *
 * Stats and patch currency mean the same thing in every game, so they live
 * here. What counts as a finished gear list, skill setup or passive plan is
 * game anatomy, so GameBuildProfile supplies those checks. Used by each game's
 * save_build MCP tool and by the web publish flow.
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
            ...GameBuildProfile::forBuild($build)->publishChecks($build),
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

        return self::check(
            'stats',
            'Stats present',
            $hasHeadline || $hasRows,
            'Add dps and ehp, or offence and defence rows under stats.',
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function patchCheck(Build $build): array
    {
        $activeVersionId = $build->game?->activeVersion()?->id;

        if ($activeVersionId === null) {
            return self::check('patch', 'Patch current', true, null);
        }

        $passed = $build->game_version_id === $activeVersionId;

        return self::check(
            'patch',
            'Patch current',
            $passed,
            $passed ? null : 'The build was saved against an older patch; re-save it on the current one.',
        );
    }

    /**
     * The shape every check returns, here and in the per-game check classes.
     *
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    public static function check(string $key, string $label, bool $passed, ?string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'detail' => $passed ? null : $detail,
        ];
    }
}
