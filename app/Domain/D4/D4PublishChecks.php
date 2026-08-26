<?php

namespace App\Domain\D4;

use App\Domain\Builds\PublishChecklist;
use App\Domain\D4\Validation\D4BuildRules;

/**
 * The Diablo IV half of the publish pre-flight: something equipped, an action
 * bar that fits, and a paragon plan.
 *
 * The shape is the point — a D4 payload keys gear by slot and has no passive
 * tree, so the PoE 2 checks report nonsense against it. These are as strict as
 * the PoE 2 ones but lenient about how much detail a finished build needs:
 * gear passes as soon as anything is equipped, and paragon is waived below the
 * level it unlocks at.
 */
class D4PublishChecks
{
    /**
     * Paragon boards unlock at level 60, so a leveling guide is allowed to
     * publish without one.
     */
    public const PARAGON_UNLOCK_LEVEL = 60;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function checks(array $payload): array
    {
        return [
            $this->gearCheck($payload),
            $this->skillsCheck($payload),
            $this->paragonCheck($payload),
        ];
    }

    /**
     * Gear is a map keyed by slot plus a weapons list. Any equipped item is
     * enough to publish; the detail names the slots still standing empty.
     *
     * @param  array<string, mixed>  $payload
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function gearCheck(array $payload): array
    {
        $gear = is_array($payload['gear'] ?? null) ? $payload['gear'] : [];

        $missing = array_values(array_filter(array_map(
            fn (string $slot) => ($gear[$slot] ?? []) === [] ? str_replace('_', ' ', $slot) : null,
            D4BuildRules::GEAR_SLOTS,
        )));

        if (($gear['weapons'] ?? []) === []) {
            $missing[] = 'weapon';
        }

        // Lenient on purpose: one equipped item is enough to publish.
        $passed = count($missing) <= count(D4BuildRules::GEAR_SLOTS);

        return PublishChecklist::check(
            'gear',
            'Gear list complete',
            $passed,
            $passed ? null : 'Missing gear for: '.implode(', ', $missing).'.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function skillsCheck(array $payload): array
    {
        $count = count(is_array($payload['equipped_skills'] ?? null) ? $payload['equipped_skills'] : []);
        $max = D4BuildRules::MAX_EQUIPPED_SKILLS;

        $detail = match (true) {
            $count === 0 => "List the equipped skills; the action bar holds up to {$max}.",
            $count > $max => "Lists {$count} equipped skills; the action bar holds {$max}.",
            default => null,
        };

        return PublishChecklist::check(
            'skills',
            'Skill bar filled',
            $detail === null,
            $detail,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key: string, label: string, passed: bool, detail: string|null}
     */
    protected function paragonCheck(array $payload): array
    {
        $boards = is_array($payload['paragon'] ?? null) ? $payload['paragon'] : [];
        $level = $payload['level'] ?? null;

        // Below the unlock level there is nothing to plan yet.
        $belowUnlock = is_numeric($level) && (int) $level < self::PARAGON_UNLOCK_LEVEL;
        $passed = $boards !== [] || $belowUnlock;

        return PublishChecklist::check(
            'paragon',
            'Paragon planned',
            $passed,
            $passed ? null : 'Add at least one paragon board with its glyph and notables.',
        );
    }
}
