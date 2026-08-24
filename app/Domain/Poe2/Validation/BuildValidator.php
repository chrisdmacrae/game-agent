<?php

namespace App\Domain\Poe2\Validation;

use App\Domain\Poe2\Poe2Context;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\Gem;
use App\Models\Poe2\PassiveNode;

/**
 * Heuristic validation of a build definition against PoE2's hard rules.
 * This is not a calculation engine: it checks legality and budget constraints
 * (support gem rules, spirit reservation, resistance targets), not DPS.
 */
class BuildValidator
{
    /** @var list<string> */
    protected array $violations = [];

    /** @var list<string> */
    protected array $warnings = [];

    /** @var list<string> */
    protected array $suggestions = [];

    public function __construct(protected Poe2Context $context) {}

    /**
     * @param  array<string, mixed>  $build
     * @return array{valid: bool, violations: list<string>, warnings: list<string>, suggestions: list<string>}
     */
    public function validate(array $build): array
    {
        $this->violations = [];
        $this->warnings = [];
        $this->suggestions = [];

        $this->checkIdentity($build);
        $this->checkSkills($build);
        $this->checkSpiritBudget($build);
        $this->checkPassives($build);
        $this->checkDefences($build);

        return [
            'valid' => $this->violations === [],
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
        ];
    }

    /** @param array<string, mixed> $build */
    protected function checkIdentity(array $build): void
    {
        $className = $build['class'] ?? null;
        $ascendancyName = $build['ascendancy'] ?? null;

        if ($className === null) {
            $this->warnings[] = 'No class specified.';

            return;
        }

        $class = CharacterClass::forVersion($this->context->versionId())
            ->whereLike('name', $className)
            ->first();

        if ($class === null) {
            $this->violations[] = "Unknown class \"{$className}\".";

            return;
        }

        if ($ascendancyName === null) {
            return;
        }

        $ascendancy = Ascendancy::forVersion($this->context->versionId())
            ->whereLike('name', $ascendancyName)
            ->first();

        if ($ascendancy === null) {
            $this->violations[] = "Unknown ascendancy \"{$ascendancyName}\".";
        } elseif (! str_starts_with(strtolower($ascendancy->class_name ?? ''), strtolower($class->name))) {
            $this->violations[] = "Ascendancy \"{$ascendancy->name}\" belongs to {$ascendancy->class_name}, not {$class->name}.";
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkSkills(array $build): void
    {
        $skills = $build['skills'] ?? [];
        $supportUsage = [];

        foreach ($skills as $index => $setup) {
            $gemName = $setup['gem'] ?? null;
            $label = $gemName ?? "skill #{$index}";

            $gem = $gemName ? $this->findGem($gemName) : null;

            if ($gemName !== null && $gem === null) {
                $this->violations[] = "Unknown gem \"{$gemName}\".";
            } elseif ($gem !== null && $gem->gem_type === 'support') {
                $this->violations[] = "\"{$gem->name}\" is a support gem; it cannot be used as a main skill.";
            } elseif ($gem !== null && ! $gem->is_released) {
                $this->warnings[] = "\"{$gem->name}\" is not currently obtainable (unreleased).";
            }

            $supports = $setup['supports'] ?? [];

            if (count($supports) > 5) {
                $this->violations[] = "\"{$label}\" has ".count($supports).' support gems; the maximum is 5 (2 by default, up to 5 with Jeweller\'s Orbs).';
            } elseif (count($supports) > 2) {
                $this->suggestions[] = "\"{$label}\" uses ".count($supports).' support sockets; sockets 3-5 require Jeweller\'s Orbs (Lesser, Greater, Perfect).';
            }

            $skillTypes = $gem !== null
                ? collect($gem->skill_details)->flatMap(fn (array $s) => $s['types'] ?? [])->unique()->all()
                : [];

            foreach ($supports as $supportName) {
                $support = $this->findGem($supportName);

                if ($support === null) {
                    $this->violations[] = "Unknown support gem \"{$supportName}\" on \"{$label}\".";

                    continue;
                }

                if ($support->gem_type !== 'support') {
                    $this->violations[] = "\"{$support->name}\" is not a support gem (it is {$support->gem_type}).";

                    continue;
                }

                $supportUsage[$support->name][] = $label;

                if ($skillTypes !== [] && ! $this->supportAccepts($support, $skillTypes)) {
                    $this->violations[] = "\"{$support->name}\" cannot support \"{$label}\": its allowed skill types don't match the skill.";
                }
            }
        }

        // PoE2 hard rule: only one copy of each support gem across the whole build.
        foreach ($supportUsage as $supportName => $usedBy) {
            if (count($usedBy) > 1) {
                $this->violations[] = "Support gem \"{$supportName}\" is used on multiple skills (".implode(', ', $usedBy).'). PoE2 allows only ONE copy of each support gem per character.';
            }
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkSpiritBudget(array $build): void
    {
        $available = $build['spirit_available'] ?? null;
        $reserved = 0;
        $reservers = [];

        foreach ($build['skills'] ?? [] as $setup) {
            $gem = isset($setup['gem']) ? $this->findGem($setup['gem']) : null;

            if ($gem === null) {
                continue;
            }

            foreach ($gem->skill_details as $skill) {
                $spirit = $skill['static']['reservations']['spirit'] ?? null;

                if ($spirit !== null) {
                    $reserved += $spirit;
                    $reservers[] = "{$gem->name} ({$spirit})";
                }
            }
        }

        if ($reserved === 0) {
            return;
        }

        if ($available === null) {
            $this->warnings[] = "Build reserves {$reserved} spirit (".implode(', ', $reservers).') but no spirit_available was provided; base spirit from the campaign is 100.';

            return;
        }

        if ($reserved > $available) {
            $this->violations[] = "Spirit over budget: {$reserved} reserved (".implode(', ', $reservers).") but only {$available} available.";
        } else {
            $this->suggestions[] = "Spirit budget: {$reserved}/{$available} reserved (".implode(', ', $reservers).').';
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkPassives(array $build): void
    {
        $passives = $build['passives'] ?? [];
        $names = array_merge($passives['keystones'] ?? [], $passives['notables'] ?? []);

        foreach ($names as $name) {
            $node = PassiveNode::forVersion($this->context->versionId())
                ->whereLike('name', $name)
                ->whereIn('kind', ['keystone', 'notable'])
                ->whereNull('ascendancy_key')
                ->first();

            if ($node === null) {
                $this->violations[] = "Passive \"{$name}\" was not found as a keystone or notable on the tree.";
            }
        }

        $level = $build['level'] ?? null;
        $pointsUsed = $passives['points_used'] ?? null;

        if ($level !== null && $pointsUsed !== null) {
            // Heuristic: roughly 1 point per level plus ~24 from quests/books.
            $budget = min((int) $level - 1, 99) + 24;

            if ($pointsUsed > $budget) {
                $this->warnings[] = "Passive points used ({$pointsUsed}) likely exceeds the budget at level {$level} (~{$budget} incl. quest rewards).";
            }
        }
    }

    /** @param array<string, mixed> $build */
    protected function checkDefences(array $build): void
    {
        $resistances = $build['resistances'] ?? null;

        if ($resistances === null) {
            return;
        }

        $tier = $build['content_tier'] ?? 'endgame';
        $target = $tier === 'campaign' ? 0 : 75;

        foreach (['fire', 'cold', 'lightning'] as $element) {
            $value = $resistances[$element] ?? null;

            if ($value !== null && $value < $target) {
                $this->warnings[] = ucfirst($element)." resistance {$value}% is below the {$target}% cap expected for {$tier} content.";
            }
        }

        if (($resistances['chaos'] ?? null) !== null && $resistances['chaos'] < 0 && $tier !== 'campaign') {
            $this->suggestions[] = "Chaos resistance is negative ({$resistances['chaos']}%); consider gearing some chaos res for endgame content.";
        }
    }

    protected function findGem(string $name): ?Gem
    {
        return Gem::forVersion($this->context->versionId())
            ->whereLike('name', $name)
            ->orderByDesc('is_released')
            ->first();
    }

    /** @param list<string> $skillTypes */
    protected function supportAccepts(Gem $support, array $skillTypes): bool
    {
        foreach ($support->skill_details as $skill) {
            $constraints = $skill['support_gem'] ?? null;

            if ($constraints === null) {
                continue;
            }

            $allowed = array_diff($constraints['allowed_types'] ?? [], ['AND', 'OR', 'NOT']);
            $excluded = array_diff($constraints['excluded_types'] ?? [], ['AND', 'OR', 'NOT']);

            if (array_intersect($excluded, $skillTypes) !== []) {
                return false;
            }

            if ($allowed === [] || array_intersect($allowed, $skillTypes) !== []) {
                return true;
            }
        }

        // No constraint data: don't block, the agent should verify manually.
        return true;
    }
}
