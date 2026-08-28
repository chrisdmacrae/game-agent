<?php

namespace App\Domain\Poe2\Ggg;

use App\Domain\Builds\BuildPayload;
use App\Domain\Poe2\Poe2Context;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\PassiveNode;
use Illuminate\Support\Collection;

/**
 * Turns a GGG `Character` into the build payload shape the rest of the app
 * speaks, so a live character and a saved build can be compared field for
 * field.
 *
 * The mapping is deliberately lossy in one direction only: everything we can
 * read is carried across, nothing is invented. Two API details drive most of
 * the code here — item names arrive wrapped in `<<set:MS>>`-style display
 * markup that has to be stripped, and gem level/quality live inside the
 * generic `properties` array rather than as fields.
 *
 * @see https://www.pathofexile.com/developer/docs/reference#type-Character
 */
class CharacterNormalizer
{
    /**
     * GGG equipment slot ids to our gear slots. Anything absent here (flasks,
     * the trinket slot, backpack contents) is not part of a build payload.
     *
     * @var array<string, string>
     */
    protected const SLOTS = [
        'Helm' => 'helmet',
        'BodyArmour' => 'body',
        'Gloves' => 'gloves',
        'Boots' => 'boots',
        'Amulet' => 'amulet',
        'Ring' => 'ring1',
        'Ring2' => 'ring2',
        'Belt' => 'belt',
        'Weapon' => 'weapon1',
        'Offhand' => 'offhand1',
        'Weapon2' => 'weapon2',
        'Offhand2' => 'offhand2',
    ];

    /** @var array<int, string> */
    protected const RARITIES = [0 => 'normal', 1 => 'magic', 2 => 'rare', 3 => 'unique'];

    /** BuildRules caps gear mods at 8; a real item never needs more. */
    protected const MAX_MODS = 8;

    public function __construct(protected Poe2Context $context) {}

    /**
     * @param  array<string, mixed>  $character
     * @return array<string, mixed>
     */
    public function normalize(array $character): array
    {
        return [
            'name' => $character['name'] ?? null,
            'realm' => $character['realm'] ?? GggApiClient::REALM,
            'league' => $character['league'] ?? null,
            'experience' => $character['experience'] ?? null,
            'is_current' => (bool) ($character['current'] ?? false),
            'build' => $this->build($character),
        ];
    }

    /**
     * The summary shown by list_my_characters — no gear, no passives.
     *
     * @param  array<string, mixed>  $character
     * @return array<string, mixed>
     */
    public function summarize(array $character): array
    {
        return [
            'name' => $character['name'] ?? null,
            'class' => $character['class'] ?? null,
            'level' => $character['level'] ?? null,
            'league' => $character['league'] ?? null,
            'is_current' => (bool) ($character['current'] ?? false),
            'is_expired' => (bool) ($character['expired'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $character
     * @return array<string, mixed>
     */
    protected function build(array $character): array
    {
        $classField = is_string($character['class'] ?? null) ? $character['class'] : null;
        $ascendancy = $this->resolveAscendancy($classField);

        return array_filter([
            // When `class` names an ascendancy we still want the base class,
            // and vice versa; whichever the API gave us fills its own field.
            'class' => $ascendancy['class'] ?? $classField,
            'ascendancy' => $ascendancy['ascendancy'] ?? null,
            'level' => $character['level'] ?? null,
            'skills' => $this->skills($character['skills'] ?? []),
            'passives' => $this->passives($character['passives'] ?? []),
            'gear' => $this->gear($character['equipment'] ?? []),
        ], fn (mixed $value) => $value !== null && $value !== []);
    }

    /**
     * The API's `class` field is documented as the base class, but has
     * historically returned the ascendancy for ascended characters. Rather
     * than guess, look the value up: if it matches an ascendancy we know, use
     * it as one and recover the base class from our own data.
     *
     * @return array{class?: string, ascendancy?: string}
     */
    protected function resolveAscendancy(?string $classField): array
    {
        if ($classField === null || $classField === '') {
            return [];
        }

        $ascendancy = Ascendancy::forVersion($this->context->versionId())
            ->whereLike('name', $classField)
            ->first();

        if ($ascendancy === null) {
            return [];
        }

        return ['class' => $ascendancy->class_name, 'ascendancy' => $ascendancy->name];
    }

    /**
     * PoE2 skill gems are items with their support gems socketed into them.
     *
     * @param  array<int, mixed>  $skills
     * @return list<array<string, mixed>>
     */
    protected function skills(array $skills): array
    {
        $normalized = [];

        foreach ($skills as $skill) {
            if (! is_array($skill)) {
                continue;
            }

            $gem = $this->itemName($skill);

            if ($gem === null) {
                continue;
            }

            $properties = $this->properties($skill);

            $normalized[] = array_filter([
                'gem' => $gem,
                'level' => $properties['Level'] ?? null,
                'quality' => $properties['Quality'] ?? null,
                'supports' => $this->supports($skill['socketedItems'] ?? []),
            ], fn (mixed $value) => $value !== null && $value !== []);
        }

        // normalize() gives supports the {name, effect} shape every other
        // reader in the app expects.
        return BuildPayload::normalize(['skills' => $normalized])['skills'];
    }

    /**
     * @param  array<int, mixed>  $socketed
     * @return list<string>
     */
    protected function supports(array $socketed): array
    {
        $supports = [];

        foreach ($socketed as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->itemName($item);

            if ($name !== null) {
                // PoE2 support gems carry no "Support" suffix; strip one if a
                // PoE1-style name ever comes back so it matches our gem data.
                $supports[] = preg_replace('/\s+Support$/', '', $name);
            }
        }

        return $supports;
    }

    /**
     * `passives.hashes` are the same node ids as the tree export we import, so
     * they drop straight into the payload. Keystone and notable names are
     * resolved from our own tree data for readability.
     *
     * @param  array<string, mixed>  $passives
     * @return array<string, mixed>
     */
    protected function passives(array $passives): array
    {
        $hashes = array_values(array_filter(
            is_array($passives['hashes'] ?? null) ? $passives['hashes'] : [],
            'is_int',
        ));

        if ($hashes === []) {
            return [];
        }

        $nodes = $this->nodesFor($hashes);

        $passives = [
            'node_ids' => $hashes,
            'points_used' => count($hashes),
        ];

        $named = [
            'keystones' => $nodes->where('kind', 'keystone')->pluck('name')->values()->all(),
            'notables' => $nodes->where('kind', 'notable')->whereNull('ascendancy_key')->pluck('name')->values()->all(),
            'ascendancy_nodes' => $nodes->whereNotNull('ascendancy_key')->pluck('name')->values()->all(),
        ];

        foreach ($named as $key => $names) {
            if ($names !== []) {
                $passives[$key] = $names;
            }
        }

        return $passives;
    }

    /**
     * @param  list<int>  $hashes
     * @return Collection<int, PassiveNode>
     */
    public function nodesFor(array $hashes): Collection
    {
        if ($hashes === []) {
            return collect();
        }

        return PassiveNode::forVersion($this->context->versionId())
            ->whereIn('node_id', $hashes)
            ->whereNot('name', '')
            ->get();
    }

    /**
     * @param  array<int, mixed>  $equipment
     * @return list<array<string, mixed>>
     */
    protected function gear(array $equipment): array
    {
        $gear = [];

        foreach ($equipment as $item) {
            if (! is_array($item)) {
                continue;
            }

            $slot = self::SLOTS[$item['inventoryId'] ?? ''] ?? null;

            if ($slot === null) {
                continue;
            }

            $gear[] = array_filter([
                'slot' => $slot,
                'rarity' => self::RARITIES[$item['frameTypeId'] ?? 0] ?? 'normal',
                'name' => $this->displayText($item['name'] ?? null),
                'base' => $this->displayText($item['baseType'] ?? $item['typeLine'] ?? null),
                'mods' => $this->mods($item),
                'runes' => $this->runes($item),
            ], fn (mixed $value) => $value !== null && $value !== [] && $value !== '');
        }

        return $gear;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function mods(array $item): array
    {
        $mods = [];

        foreach (['implicitMods', 'runeMods', 'explicitMods'] as $key) {
            foreach (is_array($item[$key] ?? null) ? $item[$key] : [] as $mod) {
                if (is_string($mod) && $mod !== '') {
                    $mods[] = $mod;
                }
            }
        }

        return array_slice($mods, 0, self::MAX_MODS);
    }

    /**
     * PoE2 gear sockets hold runes and soul cores. One entry per socket in
     * socket order, null for an empty one, which is how the build page renders
     * "empty socket".
     *
     * @param  array<string, mixed>  $item
     * @return list<string|null>
     */
    protected function runes(array $item): array
    {
        $sockets = is_array($item['sockets'] ?? null) ? $item['sockets'] : [];

        if ($sockets === []) {
            return [];
        }

        $byIndex = [];

        foreach (is_array($item['socketedItems'] ?? null) ? $item['socketedItems'] : [] as $socketed) {
            if (is_array($socketed)) {
                $byIndex[$socketed['socket'] ?? count($byIndex)] = $this->itemName($socketed);
            }
        }

        $runes = [];

        foreach (array_keys($sockets) as $index) {
            $runes[] = $byIndex[$index] ?? null;
        }

        return $runes;
    }

    /**
     * A gem or rune's name: the base type, falling back to the type line.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemName(array $item): ?string
    {
        $name = $this->displayText($item['baseType'] ?? null)
            ?? $this->displayText($item['typeLine'] ?? null)
            ?? $this->displayText($item['name'] ?? null);

        return $name === '' ? null : $name;
    }

    /**
     * Item names and type lines come back wrapped in display markup —
     * `<<set:MS>><<set:M>><<set:S>>Grasping Mail`. Strip it, or every name
     * fails to match our own data.
     */
    protected function displayText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $stripped = trim(preg_replace('/<<[^>]*>>/', '', $value) ?? '');

        return $stripped === '' ? null : $stripped;
    }

    /**
     * Gem level and quality live in the generic properties array, as display
     * strings ("20", "+18%").
     *
     * @param  array<string, mixed>  $item
     * @return array<string, int>
     */
    protected function properties(array $item): array
    {
        $properties = [];

        foreach (is_array($item['properties'] ?? null) ? $item['properties'] : [] as $property) {
            if (! is_array($property) || ! is_string($property['name'] ?? null)) {
                continue;
            }

            $raw = $property['values'][0][0] ?? null;

            if (is_string($raw) && preg_match('/-?\d+/', $raw, $matches) === 1) {
                $properties[$property['name']] = (int) $matches[0];
            }
        }

        return $properties;
    }
}
