<?php

namespace App\Domain\D4;

use App\Domain\D4\Validation\D4BuildRules;

/**
 * Canonicalises a Diablo IV build payload before it is stored, the way
 * BuildPayload::normalize() does for PoE2.
 *
 * The payload is written by clients with different habits — the MCP tools send
 * terse shapes, import_build sends a best-effort mapping of a Maxroll planner,
 * and the web editor will send its own — so everything stored goes through
 * here and consumers see exactly one shape: lists are lists, strings are
 * trimmed, and empty entries are gone rather than rendering as blank rows.
 *
 * This is deliberately a D4-only class. The shared BuildPayload still owns the
 * PoE2 payload; a later change gives the web editor per-game dispatch.
 */
class D4BuildPayload
{
    /**
     * Gear keys the MCP or an importer might send, mapped onto the canonical
     * slot names.
     *
     * @var array<string, string>
     */
    protected const GEAR_ALIASES = [
        'helmet' => 'helm',
        'head' => 'helm',
        'body' => 'chest',
        'torso' => 'chest',
        'chest_armor' => 'chest',
        'legs' => 'pants',
        'leggings' => 'pants',
        'feet' => 'boots',
        'hands' => 'gloves',
        'necklace' => 'amulet',
        'ring1' => 'ring_1',
        'ring2' => 'ring_2',
        'weapon' => 'weapons',
    ];

    /**
     * @param  array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function normalize(array $build): array
    {
        $build['class'] = self::text($build['class'] ?? null);
        $build['seasonal_power'] = self::text($build['seasonal_power'] ?? null);

        $build['equipped_skills'] = self::equippedSkills($build['equipped_skills'] ?? null);
        $build['skill_points'] = self::skillPoints($build['skill_points'] ?? null);
        $build['paragon'] = self::paragon($build['paragon'] ?? null);
        $build['gear'] = self::gear($build['gear'] ?? null);
        $build['mercenary'] = self::mercenary($build['mercenary'] ?? null);
        $build['resistances'] = self::resistances($build['resistances'] ?? null);

        return self::withoutEmpty($build);
    }

    /**
     * The equipped skills on the action bar, as a list of objects.
     *
     * @return list<array<string, mixed>>
     */
    public static function equippedSkills(mixed $skills): array
    {
        if (! is_array($skills)) {
            return [];
        }

        $normalized = [];

        foreach ($skills as $skill) {
            if (is_string($skill)) {
                $skill = ['skill' => $skill];
            }

            if (! is_array($skill)) {
                continue;
            }

            $name = self::text($skill['skill'] ?? null);

            if ($name === null) {
                continue;
            }

            $normalized[] = self::withoutEmpty([
                'skill' => $name,
                'rank' => self::int($skill['rank'] ?? null),
                'role' => self::text($skill['role'] ?? null),
                'modifiers' => self::textList($skill['modifiers'] ?? null),
                'reported' => self::text($skill['reported'] ?? null),
            ]);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function skillPoints(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        $normalized = [];

        foreach ($points as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = self::text($entry['skill'] ?? null);

            if ($name === null) {
                continue;
            }

            $normalized[] = self::withoutEmpty([
                'skill' => $name,
                'points' => self::int($entry['points'] ?? null),
            ]);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function paragon(mixed $paragon): array
    {
        if (! is_array($paragon)) {
            return [];
        }

        $normalized = [];

        foreach ($paragon as $entry) {
            if (is_string($entry)) {
                $entry = ['board' => $entry];
            }

            if (! is_array($entry)) {
                continue;
            }

            $board = self::text($entry['board'] ?? null);

            if ($board === null) {
                continue;
            }

            $normalized[] = self::withoutEmpty([
                'board' => $board,
                'rotation' => self::int($entry['rotation'] ?? null),
                'glyph' => self::text($entry['glyph'] ?? null),
                'glyph_level' => self::int($entry['glyph_level'] ?? null),
                'notables' => self::textList($entry['notables'] ?? null),
            ]);
        }

        return $normalized;
    }

    /**
     * Gear is a map keyed by slot, plus a flexible `weapons` list. Aliases the
     * model might reach for are folded onto the canonical keys and anything
     * unrecognised is dropped, so the gear screen never has to guess.
     *
     * @return array<string, mixed>
     */
    protected static function gear(mixed $gear): array
    {
        if (! is_array($gear)) {
            return [];
        }

        $canonical = [];

        foreach ($gear as $slot => $item) {
            if (! is_string($slot)) {
                continue;
            }

            $key = strtolower(trim($slot));
            $key = self::GEAR_ALIASES[$key] ?? $key;

            if ($key === 'weapons') {
                $weapons = array_values(array_filter(
                    array_map(self::item(...), is_array($item) ? $item : [$item]),
                    fn (array $weapon) => $weapon !== [],
                ));

                if ($weapons !== []) {
                    $canonical['weapons'] = $weapons;
                }

                continue;
            }

            if (! in_array($key, D4BuildRules::GEAR_SLOTS, true)) {
                continue;
            }

            $normalized = self::item($item);

            if ($normalized !== []) {
                $canonical[$key] = $normalized;
            }
        }

        // Keep the map in slot order so the gear screen and diffs are stable.
        $ordered = [];

        foreach (D4BuildRules::GEAR_SLOTS as $slot) {
            if (isset($canonical[$slot])) {
                $ordered[$slot] = $canonical[$slot];
            }
        }

        if (isset($canonical['weapons'])) {
            $ordered['weapons'] = $canonical['weapons'];
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(mixed $item): array
    {
        if (is_string($item)) {
            $item = ['name' => $item];
        }

        if (! is_array($item)) {
            return [];
        }

        $rarity = self::text($item['rarity'] ?? null);

        return self::withoutEmpty([
            'name' => self::text($item['name'] ?? null),
            'item_type' => self::text($item['item_type'] ?? null),
            'rarity' => $rarity === null ? null : strtolower($rarity),
            'aspect' => self::text($item['aspect'] ?? null),
            'affixes' => self::textList($item['affixes'] ?? null),
            'greater_affixes' => self::int($item['greater_affixes'] ?? null),
            'tempered' => self::tempered($item['tempered'] ?? null),
            'masterwork_level' => self::int($item['masterwork_level'] ?? null),
            'runes' => self::textList($item['runes'] ?? null),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function tempered(mixed $tempered): array
    {
        if (! is_array($tempered)) {
            return [];
        }

        $normalized = [];

        foreach ($tempered as $entry) {
            if (is_string($entry)) {
                $entry = ['affix' => $entry];
            }

            if (! is_array($entry)) {
                continue;
            }

            $affix = self::text($entry['affix'] ?? null);

            if ($affix === null) {
                continue;
            }

            $normalized[] = self::withoutEmpty([
                'affix' => $affix,
                'tier' => self::int($entry['tier'] ?? null),
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    protected static function mercenary(mixed $mercenary): array
    {
        if (! is_array($mercenary)) {
            return [];
        }

        return self::withoutEmpty([
            'hired' => self::text($mercenary['hired'] ?? null),
            'reinforcement' => self::text($mercenary['reinforcement'] ?? null),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected static function resistances(mixed $resistances): array
    {
        if (! is_array($resistances)) {
            return [];
        }

        $normalized = [];

        foreach (D4BuildRules::RESISTANCES as $element) {
            $value = self::int($resistances[$element] ?? null);

            if ($value !== null) {
                $normalized[$element] = $value;
            }
        }

        return $normalized;
    }

    /**
     * A list of trimmed, non-empty strings.
     *
     * @return list<string>
     */
    protected static function textList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $list = [];

        foreach ($values as $value) {
            if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            }

            $text = self::text($value);

            if ($text !== null) {
                $list[] = $text;
            }
        }

        return $list;
    }

    protected static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Drop nulls and empty collections so the stored payload only carries what
     * the model actually said.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function withoutEmpty(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value) => $value !== null && $value !== '' && $value !== [],
        );
    }
}
