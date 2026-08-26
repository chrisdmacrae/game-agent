<?php

namespace App\Domain\D4\Import;

use App\Domain\D4\D4BuildPayload;
use App\Domain\D4\D4Context;
use App\Domain\D4\D4ParagonGraph;
use App\Domain\D4\Validation\D4BuildRules;
use App\Models\D4\Affix;
use App\Models\D4\Aspect;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads a Maxroll Diablo IV planner and maps it, best effort, onto this app's
 * build payload.
 *
 * The planner API answers `{id, date, name, class, data}` where `data` is
 * STRINGIFIED JSON holding `profiles[]` (the planner's variants) and a shared
 * `items` pool keyed by number. A profile carries `class` (an index into the
 * eight-class order the game files use), `level`, `items` (slot number -> item
 * pool key), `skillBar` (six power keys), `skillTree.steps[]` and
 * `paragon.steps[]`; only the LAST step of each is the finished build.
 *
 * Every id in there is a game-files key, which is exactly what the importer
 * stores on `raw.key` (`Barbarian_Whirlwind`, `2HAxe_Unique_Barb_001`,
 * `Rare_001_Intelligence_Main`) or, for aspects and affixes, an sno id. So the
 * mapping resolves against the imported data rather than trusting Maxroll's
 * display strings, and anything that does not resolve is reported in
 * `unmapped` instead of being invented.
 */
class MaxrollPlanner
{
    /**
     * Planner ids are short slugs, e.g. "rf5dmg0x".
     */
    public const ID_PATTERN = '/^[A-Za-z0-9_-]{4,32}$/';

    public const CACHE_TTL_SECONDS = 86400;

    /**
     * The class mask order used by every 8-wide class array in the game files,
     * and by the planner's numeric `class`.
     *
     * @var list<string>
     */
    public const CLASS_ORDER = [
        'Sorcerer',
        'Druid',
        'Barbarian',
        'Rogue',
        'Necromancer',
        'Spiritborn',
        'Paladin',
        'Warlock',
    ];

    /**
     * Item id prefix -> payload gear slot. Anything else that is not a
     * talisman or charm is treated as a weapon.
     *
     * @var array<string, string>
     */
    protected const SLOT_PREFIXES = [
        'Helm' => 'helm',
        'Chest' => 'chest',
        'Gloves' => 'gloves',
        'Pants' => 'pants',
        'Boots' => 'boots',
        'Amulet' => 'amulet',
    ];

    /** @var list<array{kind: string, source: string, reason: string}> */
    protected array $unmapped = [];

    public function __construct(protected D4Context $context) {}

    public function enabled(): bool
    {
        return (bool) config('games.diablo-4.maxroll_import_enabled');
    }

    /**
     * Pull the planner id out of a URL, or accept a bare id.
     *
     * Only the two shapes that actually carry an id are recognised — the
     * public planner page and the profiles API — so a link to the planner
     * index is rejected rather than read as a planner called "planner".
     */
    public function parseId(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (str_contains($input, '/')) {
            $matched = preg_match(
                '#/(?:d4/planner|profiles/d4)/([A-Za-z0-9_-]{4,32})#',
                $input,
                $matches,
            ) === 1;

            return $matched ? $matches[1] : null;
        }

        return preg_match(self::ID_PATTERN, $input) === 1 ? $input : null;
    }

    public function sourceUrl(string $id): string
    {
        return "https://planners.maxroll.gg/profiles/d4/{$id}";
    }

    /**
     * Fetch and decode the planner envelope, cached for a day.
     *
     * @return array<string, mixed>
     */
    public function fetch(string $id): array
    {
        return Cache::remember(
            "d4.maxroll.planner.{$id}",
            self::CACHE_TTL_SECONDS,
            function () use ($id): array {
                $response = Http::withHeaders(['User-Agent' => config('games.diablo-4.user_agent')])
                    ->timeout(30)
                    ->retry(2, 1000, throw: false)
                    ->get($this->sourceUrl($id));

                if (! $response->successful()) {
                    throw new RuntimeException("Maxroll returned HTTP {$response->status()} for planner \"{$id}\".");
                }

                $envelope = $response->json();

                if (! is_array($envelope) || ! isset($envelope['data'])) {
                    throw new RuntimeException("Maxroll returned an unexpected payload for planner \"{$id}\".");
                }

                return $envelope;
            },
        );
    }

    /**
     * Map one variant of a planner envelope onto a build payload.
     *
     * @param  array<string, mixed>  $envelope
     * @param  int|null  $variant  index into the planner's profiles, defaults to the first
     * @return array{payload: array<string, mixed>, variant: array{index: int, name: string|null}, variants: list<string>, unmapped: list<array{kind: string, source: string, reason: string}>}
     */
    public function map(array $envelope, ?int $variant = null): array
    {
        $this->unmapped = [];

        $data = $this->decodeData($envelope);
        $profiles = array_values(array_filter($data['profiles'] ?? [], is_array(...)));

        if ($profiles === []) {
            throw new RuntimeException('This planner has no build variants in it.');
        }

        $index = $variant !== null && isset($profiles[$variant]) ? $variant : 0;

        if ($variant !== null && $variant !== $index) {
            $this->unmapped[] = $this->note('variant', (string) $variant, 'That variant index does not exist; the first variant was mapped instead.');
        }

        $profile = $profiles[$index];
        $pool = is_array($data['items'] ?? null) ? $data['items'] : [];

        $payload = [
            'class' => $this->className($envelope, $profile),
            'level' => is_numeric($profile['level'] ?? null) ? (int) $profile['level'] : null,
            'content_tier' => in_array($envelope['category'] ?? null, D4BuildRules::CONTENT_TIERS, true)
                ? $envelope['category']
                : null,
            'equipped_skills' => $this->equippedSkills($profile),
            'paragon' => $this->paragon($profile),
            'gear' => $this->gear($profile, $pool),
            'mercenary' => $this->mercenary($profile),
        ];

        return [
            'payload' => D4BuildPayload::normalize($payload),
            'variant' => [
                'index' => $index,
                'name' => is_string($profile['name'] ?? null) ? $profile['name'] : null,
            ],
            'variants' => array_map(
                fn (array $candidate, int $i) => is_string($candidate['name'] ?? null) ? $candidate['name'] : "variant {$i}",
                $profiles,
                array_keys($profiles),
            ),
            'unmapped' => $this->unmapped,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    protected function decodeData(array $envelope): array
    {
        $data = $envelope['data'] ?? null;

        // `data` is stringified JSON in the live API, but tolerate a decoded
        // object so a caller can hand us an already-parsed envelope.
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            throw new RuntimeException('The planner\'s `data` field could not be decoded.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @param  array<string, mixed>  $profile
     */
    protected function className(array $envelope, array $profile): ?string
    {
        $name = $envelope['class'] ?? null;

        if (is_string($name) && in_array($name, D4BuildRules::CLASSES, true)) {
            return $name;
        }

        $index = $profile['class'] ?? null;

        return is_numeric($index) ? (self::CLASS_ORDER[(int) $index] ?? null) : null;
    }

    /**
     * The six action bar entries are power keys, e.g. "Barbarian_Whirlwind".
     * Only skills that resolve against the imported data make it into the
     * payload; the rest are reported so the model can look them up itself.
     *
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    protected function equippedSkills(array $profile): array
    {
        $skills = [];

        foreach ($profile['skillBar'] ?? [] as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $skill = Skill::forVersion($this->context->versionId())
                ->where('raw->key', $key)
                ->orderByDesc('is_released')
                ->first();

            if ($skill === null) {
                $this->unmapped[] = $this->note('skill', $key, 'No skill with this game-files key is in the imported data; find it with search_skills and add it yourself.');

                continue;
            }

            $skills[] = ['skill' => $skill->name];
        }

        return $skills;
    }

    /**
     * The last paragon step is the finished allocation. Each board entry is
     * {id, nodes, rotation, glyph, glyphLevel}; rotation is a quarter-turn
     * count, which the payload stores in degrees.
     *
     * @param  array<string, mixed>  $profile
     * @return list<array<string, mixed>>
     */
    protected function paragon(array $profile): array
    {
        $step = $this->lastStep($profile['paragon'] ?? null);
        $boards = [];

        foreach ($step as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null)) {
                continue;
            }

            $board = $this->findByKey(ParagonBoard::class, $entry['id']);

            if ($board === null) {
                $this->unmapped[] = $this->note('paragon_board', $entry['id'], 'No paragon board with this game-files key is in the imported data.');

                continue;
            }

            $mapped = [
                'board' => $board->name,
                'rotation' => is_numeric($entry['rotation'] ?? null)
                    ? ((int) $entry['rotation'] % 4) * 90
                    : null,
            ];

            $nodes = $this->paragonNodes($board, $entry);

            if ($nodes !== []) {
                $mapped['nodes'] = $nodes;

                $gate = $this->paragonEntryGate($board, $nodes, isFirst: $boards === []);

                if ($gate !== null) {
                    $mapped['attach'] = ['gate' => $gate];
                }
            }

            $glyphKey = $entry['glyph'] ?? null;

            if (is_string($glyphKey) && $glyphKey !== '') {
                $glyph = $this->findByKey(ParagonGlyph::class, $glyphKey);

                if ($glyph === null) {
                    $this->unmapped[] = $this->note('glyph', $glyphKey, 'No glyph with this game-files key is in the imported data.');
                } else {
                    $mapped['glyph'] = $glyph->name;
                    $level = $entry['glyphLevel'] ?? null;

                    if (is_numeric($level) && (int) $level >= 1 && (int) $level <= 200) {
                        $mapped['glyph_level'] = (int) $level;
                    }
                }
            }

            $boards[] = $mapped;
        }

        return array_slice($boards, 0, D4BuildRules::MAX_PARAGON_BOARDS);
    }

    /**
     * The planner's `nodes` is a map of flat row-major grid indices (the same
     * order the game's arEntries uses) to an allocation flag. Verified against
     * the board layout: index 10 on the 21-wide Barbarian start board is its
     * single gate at (0,10). Indices that fall outside the grid or on empty
     * cells are reported rather than guessed at.
     *
     * @param  array<string, mixed>  $entry
     * @return list<array{row: int, col: int}>
     */
    protected function paragonNodes(ParagonBoard $board, array $entry): array
    {
        $allocated = $entry['nodes'] ?? null;

        if (! is_array($allocated) || $allocated === []) {
            return [];
        }

        $grid = is_array($board->grid) ? $board->grid : [];
        $width = (int) ($board->raw['width'] ?? count($grid));

        if ($width <= 0) {
            return [];
        }

        $nodes = [];

        foreach ($allocated as $index => $taken) {
            if (! is_numeric($index) || ! $taken) {
                continue;
            }

            $row = intdiv((int) $index, $width);
            $col = (int) $index % $width;

            if (! is_array($grid[$row][$col] ?? null)) {
                $this->unmapped[] = $this->note(
                    'paragon_node',
                    "{$board->name}#{$index}",
                    'This planner node index lands on empty space in the imported grid; the board layout may have changed between patches.',
                );

                continue;
            }

            $nodes[] = ['row' => $row, 'col' => $col];
        }

        return $nodes;
    }

    /**
     * The gate the board is entered through: the allocated gate cell from
     * which the rest of the allocation is reachable. The planner does not
     * store attachment explicitly, but a legal allocation always purchases its
     * entry gate, so the best-connected allocated gate is the entry.
     *
     * @param  list<array{row: int, col: int}>  $nodes
     * @return array{row: int, col: int}|null
     */
    protected function paragonEntryGate(ParagonBoard $board, array $nodes, bool $isFirst): ?array
    {
        if ($isFirst) {
            return null; // The start board has no attachment.
        }

        $grid = is_array($board->grid) ? $board->grid : [];
        $graph = new D4ParagonGraph($this->context);

        $best = null;
        $bestUnreached = PHP_INT_MAX;

        foreach ($nodes as $node) {
            $cell = $graph->cellAt($grid, $node['row'], $node['col']);

            if ($cell === null || ($cell['is_gate'] ?? false) !== true) {
                continue;
            }

            $unreached = count($graph->reachability($grid, $node, $nodes)['unreached']);

            if ($unreached < $bestUnreached) {
                $best = $node;
                $bestUnreached = $unreached;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string|int, mixed>  $pool
     * @return array<string, mixed>
     */
    protected function gear(array $profile, array $pool): array
    {
        $gear = [];
        $weapons = [];
        $ringSlot = 1;

        foreach ($profile['items'] ?? [] as $itemKey) {
            $item = $pool[(string) $itemKey] ?? null;

            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            $id = $item['id'];
            $prefix = Str::before($id, '_');

            if (str_starts_with($id, 'Talisman')) {
                $this->unmapped[] = $this->note('item', $id, 'Talismans and charms have no home in this build payload yet.');

                continue;
            }

            $mapped = $this->item($item, $id);

            if ($prefix === 'Ring') {
                if ($ringSlot > 2) {
                    $this->unmapped[] = $this->note('item', $id, 'More than two rings are equipped in this planner variant.');

                    continue;
                }

                $gear['ring_'.$ringSlot++] = $mapped;

                continue;
            }

            $slot = self::SLOT_PREFIXES[$prefix] ?? null;

            if ($slot !== null) {
                $gear[$slot] = $mapped;

                continue;
            }

            if (count($weapons) < D4BuildRules::MAX_WEAPONS) {
                $weapons[] = $mapped;
            } else {
                $this->unmapped[] = $this->note('item', $id, 'More weapons are equipped than the payload carries.');
            }
        }

        if ($weapons !== []) {
            $gear['weapons'] = $weapons;
        }

        return $gear;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function item(array $item, string $id): array
    {
        // Planner ids carry a "_x1" variant suffix the game files do not.
        $key = (string) preg_replace('/_x\d+$/', '', $id);
        $rarity = $this->rarity($id);

        $mapped = [
            'rarity' => $rarity,
            'name' => is_string($item['name'] ?? null) && $item['name'] !== '' ? $item['name'] : null,
            'item_type' => null,
        ];

        if (in_array($rarity, ['unique', 'mythic'], true)) {
            $unique = $this->findByKey(UniqueItem::class, $key);

            if ($unique === null) {
                $this->unmapped[] = $this->note('unique', $key, 'No unique item with this game-files key is in the imported data; confirm the name with search_uniques.');
            } else {
                $mapped['name'] = $unique->name;
                $mapped['item_type'] = $unique->item_type;
                $mapped['rarity'] = $unique->is_mythic ? 'mythic' : 'unique';
            }
        }

        $mapped['item_type'] ??= Str::before($key, '_');
        $mapped['aspect'] = $this->aspectName($item, $id);
        $mapped['affixes'] = $this->affixEntries($item['explicits'] ?? []);
        $mapped['greater_affixes'] = $this->greaterCount($item);
        $mapped['tempered'] = array_map(
            fn (string $affix) => ['affix' => $affix],
            $this->affixNames($item['tempered'] ?? []),
        );
        $mapped['runes'] = $this->runes($item);

        return $mapped;
    }

    protected function rarity(string $id): string
    {
        return match (true) {
            str_contains($id, 'MythicUnique') => 'mythic',
            str_contains($id, '_Unique_') => 'unique',
            str_contains($id, '_Legendary_') => 'legendary',
            str_contains($id, '_Magic_') => 'magic',
            str_contains($id, '_Rare') => 'rare',
            default => 'common',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function aspectName(array $item, string $id): ?string
    {
        $nid = $item['aspects'][0]['nid'] ?? null;

        if (! is_numeric($nid)) {
            return null;
        }

        $aspect = Aspect::forVersion($this->context->versionId())
            ->where('sno_id', (int) $nid)
            ->first();

        if ($aspect === null) {
            $this->unmapped[] = $this->note('aspect', (string) $nid, "The aspect on {$id} (sno {$nid}) is not in the imported data.");

            return null;
        }

        return $aspect->name;
    }

    /**
     * Rolled explicits, kept structured so the stat calculator can count
     * them: the imported affix key, the display name as the text line, and
     * the planner's greater flag.
     *
     * @return list<array<string, mixed>>
     */
    protected function affixEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $structured = [];

        foreach ($entries as $entry) {
            $nid = is_array($entry) ? ($entry['nid'] ?? null) : null;

            if (! is_numeric($nid)) {
                continue;
            }

            $affix = Affix::forVersion($this->context->versionId())
                ->where('raw->sno_id', (int) $nid)
                ->first();

            if ($affix === null) {
                $this->unmapped[] = $this->note('affix', (string) $nid, 'This affix sno is not in the imported data.');

                continue;
            }

            $structured[] = array_filter([
                'affix' => $affix->key,
                'text' => $affix->name,
                'greater' => ($entry['greater'] ?? false) === true ? true : null,
            ], fn (mixed $value) => $value !== null && $value !== '');
        }

        return array_slice($structured, 0, 8);
    }

    /**
     * Affix entries reference an affix by its sno id, which the importer keeps
     * on `raw.sno_id`. Tempered affixes usually have no display name, so the
     * game-files key stands in.
     *
     * @return list<string>
     */
    protected function affixNames(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $names = [];

        foreach ($entries as $entry) {
            $nid = is_array($entry) ? ($entry['nid'] ?? null) : null;

            if (! is_numeric($nid)) {
                continue;
            }

            $affix = Affix::forVersion($this->context->versionId())
                ->where('raw->sno_id', (int) $nid)
                ->first();

            if ($affix === null) {
                $this->unmapped[] = $this->note('affix', (string) $nid, 'This affix sno is not in the imported data.');

                continue;
            }

            $names[] = $affix->name ?: $affix->key;
        }

        return array_slice($names, 0, 8);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function greaterCount(array $item): int
    {
        $count = 0;

        foreach (array_merge($item['explicits'] ?? [], $item['tempered'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['greater'] ?? false) === true) {
                $count++;
            }
        }

        return min($count, 4);
    }

    /**
     * Sockets hold either runes or gems; only the runeword goes in the
     * payload.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function runes(array $item): array
    {
        $runes = [];

        foreach ($item['sockets'] ?? [] as $socket) {
            if (is_string($socket) && str_starts_with($socket, 'Rune_')) {
                $runes[] = $socket;
            }
        }

        return array_slice($runes, 0, 2);
    }

    /**
     * Mercenaries are not imported as their own table, so the game-files id is
     * humanised: "MercenaryClass_BountyHunter" -> "Bounty Hunter".
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, string>
     */
    protected function mercenary(array $profile): array
    {
        $mercenary = $profile['mercenary'] ?? null;

        if (! is_array($mercenary)) {
            return [];
        }

        return array_filter([
            'hired' => $this->humanise($mercenary['id'] ?? null),
            'reinforcement' => $this->humanise($mercenary['support'] ?? null),
        ], fn (?string $value) => $value !== null);
    }

    protected function humanise(mixed $id): ?string
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        return Str::headline(Str::after($id, 'MercenaryClass_')) ?: null;
    }

    /**
     * The planner records the build as an ordered list of steps; only the last
     * one is the finished allocation.
     *
     * @return list<mixed>
     */
    protected function lastStep(mixed $section): array
    {
        $steps = is_array($section) ? ($section['steps'] ?? []) : [];

        if (! is_array($steps) || $steps === []) {
            return [];
        }

        $last = end($steps);
        $data = is_array($last) ? ($last['data'] ?? []) : [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * Resolve a row by the game-files key the importer stored on `raw.key`.
     * Maxroll drops the leading zero on some board ids (`Paragon_Spirit_0`
     * against the files' `Paragon_Spirit_00`), so a zero-padded retry follows
     * the exact match.
     *
     * @template TModel of \App\Models\D4\D4Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel|null
     */
    protected function findByKey(string $model, string $key)
    {
        $row = $model::forVersion($this->context->versionId())
            ->where('raw->key', $key)
            ->orderByDesc('is_released')
            ->first();

        if ($row !== null) {
            return $row;
        }

        if (preg_match('/^(.*?)(\d+)$/', $key, $matches) !== 1) {
            return null;
        }

        return $model::forVersion($this->context->versionId())
            ->where('raw->key', $matches[1].str_pad($matches[2], 2, '0', STR_PAD_LEFT))
            ->orderByDesc('is_released')
            ->first();
    }

    /**
     * @return array{kind: string, source: string, reason: string}
     */
    protected function note(string $kind, string $source, string $reason): array
    {
        return ['kind' => $kind, 'source' => $source, 'reason' => $reason];
    }
}
