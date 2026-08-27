<?php

namespace App\Domain\Poe2\Ggg;

use App\Domain\Builds\BuildPayload;
use App\Models\Poe2\PassiveNode;

/**
 * A deterministic diff between a live character (normalised by
 * CharacterNormalizer) and a saved build payload.
 *
 * The point is to hand the assistant facts instead of two JSON blobs to
 * eyeball: what is missing, what is extra, and where. Every entry carries a
 * `kind` so suggestions can be ranked without re-reading the payloads.
 *
 * Nothing here judges *quality* — that is the assistant's job with the game
 * data tools. This only reports differences.
 */
class CharacterBuildDiff
{
    public function __construct(protected CharacterNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $character  a normalize() result
     * @param  array<string, mixed>  $build  a stored build payload
     * @return array<string, mixed>
     */
    public function compare(array $character, array $build): array
    {
        $characterBuild = is_array($character['build'] ?? null) ? $character['build'] : [];

        $identity = $this->identity($characterBuild, $build);
        $passives = $this->passives($characterBuild, $build);
        $skills = $this->skills($characterBuild, $build);
        $gear = $this->gear($characterBuild, $build);

        return [
            'character' => [
                'name' => $character['name'] ?? null,
                'league' => $character['league'] ?? null,
                'level' => $characterBuild['level'] ?? null,
            ],
            'identity' => $identity,
            'passives' => $passives,
            'skills' => $skills,
            'gear' => $gear,
            'gap_count' => count($identity)
                + count($passives['missing'] ?? [])
                + count($skills['missing_gems'] ?? [])
                + count($skills['supports'] ?? [])
                + count($gear),
            // Said explicitly so the assistant does not claim a comparison the
            // data cannot support.
            'not_comparable' => [
                'The character API exposes no computed stats: resistances, DPS, EHP and spirit cannot be compared. Ask the user for their in-game numbers.',
                'Character data reflects the last time the character was saved by the game, not live state.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $character
     * @param  array<string, mixed>  $build
     * @return list<array<string, mixed>>
     */
    protected function identity(array $character, array $build): array
    {
        $differences = [];

        foreach (['class', 'ascendancy'] as $field) {
            $theirs = $character[$field] ?? null;
            $wanted = $build[$field] ?? null;

            if ($wanted === null || $theirs === null) {
                continue;
            }

            if (mb_strtolower((string) $theirs) !== mb_strtolower((string) $wanted)) {
                $differences[] = [
                    'kind' => $field.'_mismatch',
                    'character' => $theirs,
                    'build' => $wanted,
                ];
            }
        }

        $theirLevel = $character['level'] ?? null;
        $buildLevel = $build['level'] ?? null;

        if (is_int($theirLevel) && is_int($buildLevel) && $theirLevel < $buildLevel) {
            $differences[] = [
                'kind' => 'below_build_level',
                'character' => $theirLevel,
                'build' => $buildLevel,
                'detail' => ($buildLevel - $theirLevel).' levels short of the build, so some passives and gear requirements are out of reach.',
            ];
        }

        return $differences;
    }

    /**
     * Compared by node id, which is the only unambiguous handle — the build
     * may also list keystone and notable names, but those are a subset.
     *
     * @param  array<string, mixed>  $character
     * @param  array<string, mixed>  $build
     * @return array<string, mixed>
     */
    protected function passives(array $character, array $build): array
    {
        $theirs = $this->nodeIds($character);
        $wanted = $this->nodeIds($build);

        if ($wanted === []) {
            return [
                'comparable' => false,
                'detail' => 'The build lists no passive node_ids, so the trees cannot be diffed. Compare the named keystones and notables instead.',
                'character_points_used' => count($theirs),
            ];
        }

        $missing = array_values(array_diff($wanted, $theirs));
        $extra = array_values(array_diff($theirs, $wanted));

        return [
            'comparable' => true,
            'character_points_used' => count($theirs),
            'build_points_used' => count($wanted),
            'missing' => $this->describeNodes($missing),
            'extra' => $this->describeNodes($extra),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    protected function nodeIds(array $payload): array
    {
        $ids = $payload['passives']['node_ids'] ?? [];

        return is_array($ids) ? array_values(array_filter($ids, 'is_int')) : [];
    }

    /**
     * Named nodes first: an unnamed small passive is noise next to a missing
     * keystone.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    protected function describeNodes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $nodes = $this->normalizer->nodesFor($ids)
            ->sortBy(fn (PassiveNode $node) => match ($node->kind) {
                'keystone' => 0,
                'notable' => 1,
                default => 2,
            });

        $described = [];

        foreach ($nodes as $node) {
            $described[] = [
                'node_id' => $node->node_id,
                'name' => $node->name,
                'kind' => $node->kind,
            ];
        }

        $unnamedCount = count($ids) - count($described);

        if ($unnamedCount > 0) {
            $described[] = ['kind' => 'small_passives', 'count' => $unnamedCount];
        }

        return $described;
    }

    /**
     * @param  array<string, mixed>  $character
     * @param  array<string, mixed>  $build
     * @return array<string, mixed>
     */
    protected function skills(array $character, array $build): array
    {
        $theirs = $this->skillsByGem($character);
        $wanted = $this->skillsByGem($build);

        $supports = [];

        foreach ($wanted as $gem => $wantedSupports) {
            if (! isset($theirs[$gem])) {
                continue;
            }

            $missing = array_values(array_diff($wantedSupports, $theirs[$gem]));
            $extra = array_values(array_diff($theirs[$gem], $wantedSupports));

            if ($missing === [] && $extra === []) {
                continue;
            }

            $supports[] = array_filter([
                'gem' => $gem,
                'missing' => $missing,
                'extra' => $extra,
            ], fn (mixed $value) => $value !== []);
        }

        return array_filter([
            'missing_gems' => array_values(array_diff(array_keys($wanted), array_keys($theirs))),
            'extra_gems' => array_values(array_diff(array_keys($theirs), array_keys($wanted))),
            'supports' => $supports,
        ], fn (mixed $value) => $value !== []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    protected function skillsByGem(array $payload): array
    {
        $skills = [];

        foreach (is_array($payload['skills'] ?? null) ? $payload['skills'] : [] as $skill) {
            if (! is_array($skill) || ! is_string($skill['gem'] ?? null)) {
                continue;
            }

            // Supports may be strings or {name, effect} objects depending on
            // when the row was written; supportNames() handles both.
            $skills[$skill['gem']] = BuildPayload::supportNames($skill);
        }

        return $skills;
    }

    /**
     * Per slot: nothing equipped, the wrong unique, or a rare that is missing
     * mods the build asked for.
     *
     * @param  array<string, mixed>  $character
     * @param  array<string, mixed>  $build
     * @return list<array<string, mixed>>
     */
    protected function gear(array $character, array $build): array
    {
        $theirs = $this->gearBySlot($character);
        $differences = [];

        foreach ($this->gearBySlot($build) as $slot => $wanted) {
            $equipped = $theirs[$slot] ?? null;

            if ($equipped === null) {
                $differences[] = array_filter([
                    'slot' => $slot,
                    'kind' => 'empty_slot',
                    'build' => $wanted['name'] ?? $wanted['base'] ?? null,
                ], fn (mixed $value) => $value !== null);

                continue;
            }

            if (($wanted['rarity'] ?? null) === 'unique' && isset($wanted['name'])) {
                $wantedName = mb_strtolower($wanted['name']);
                $theirName = mb_strtolower((string) ($equipped['name'] ?? ''));

                if ($wantedName !== $theirName) {
                    $differences[] = [
                        'slot' => $slot,
                        'kind' => 'different_item',
                        'character' => $equipped['name'] ?? $equipped['base'] ?? null,
                        'build' => $wanted['name'],
                    ];
                }

                continue;
            }

            $missingMods = $this->missingMods($equipped, $wanted);

            if ($missingMods !== []) {
                $differences[] = [
                    'slot' => $slot,
                    'kind' => 'missing_mods',
                    'missing' => $missingMods,
                ];
            }
        }

        return $differences;
    }

    /**
     * Build mods are written as intent ("+ to maximum Life"), item mods as
     * rolled text ("+89 to maximum Life"), so this matches on the words rather
     * than the string: a build mod counts as present when the item carries a
     * mod containing all of its non-numeric words.
     *
     * @param  array<string, mixed>  $equipped
     * @param  array<string, mixed>  $wanted
     * @return list<string>
     */
    protected function missingMods(array $equipped, array $wanted): array
    {
        $itemMods = array_map(
            fn (string $mod) => mb_strtolower($mod),
            array_filter(is_array($equipped['mods'] ?? null) ? $equipped['mods'] : [], 'is_string'),
        );

        $missing = [];

        foreach (is_array($wanted['mods'] ?? null) ? $wanted['mods'] : [] as $mod) {
            if (! is_string($mod)) {
                continue;
            }

            $words = array_filter(
                preg_split('/[^a-z]+/', mb_strtolower($mod)) ?: [],
                fn (string $word) => mb_strlen($word) > 2,
            );

            if ($words === []) {
                continue;
            }

            $found = false;

            foreach ($itemMods as $itemMod) {
                $hits = array_filter($words, fn (string $word) => str_contains($itemMod, $word));

                if (count($hits) === count($words)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $missing[] = $mod;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    protected function gearBySlot(array $payload): array
    {
        $gear = [];

        foreach (is_array($payload['gear'] ?? null) ? $payload['gear'] : [] as $item) {
            if (is_array($item) && is_string($item['slot'] ?? null)) {
                $gear[$item['slot']] = $item;
            }
        }

        return $gear;
    }
}
