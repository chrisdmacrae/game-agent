<?php

namespace App\Domain\Poe2;

/**
 * Builds a compact render payload of the passive tree (positions, kinds,
 * names, sprite frames, edges) from the official tree export, written to
 * public/games/poe2/tree/render.json for client-side SVG rendering.
 *
 * Includes ascendancy nodes tagged with their ascendancy key ('a') so the
 * client can show the cluster for a build's chosen ascendancy.
 *
 * Keys are shortened to keep the payload small: id, x, y, k(ind), n(ame),
 * a(scendancy), s(prite frame), ci (class start indices).
 */
class TreeRender
{
    /** Sprite sheet sections to try per node kind, most specific first. */
    protected const SPRITE_PREFIXES = [
        'keystone' => ['keystoneActive', 'notableActive', 'normalActive'],
        'notable' => ['notableActive', 'keystoneActive', 'normalActive'],
        'default' => ['normalActive', 'notableActive', 'keystoneActive'],
    ];

    /**
     * @param  array<string, mixed>  $tree  decoded official tree data.json
     * @param  array<string, mixed>  $sprite  decoded skills.json sprite sheet data
     * @return array<string, mixed>
     */
    public function build(array $tree, array $sprite = []): array
    {
        $frames = $sprite['frames'] ?? [];

        $nodes = [];
        $included = [];

        foreach ($tree['nodes'] ?? [] as $nodeId => $node) {
            if (! is_numeric($nodeId) || ! isset($node['x'], $node['y'])) {
                continue;
            }

            $kind = match (true) {
                isset($node['classStartIndex']) => 'start',
                ($node['isAscendancyStart'] ?? false) => 'ascstart',
                ($node['isKeystone'] ?? false) => 'keystone',
                ($node['isJewelSocket'] ?? false) => 'jewel',
                ($node['isNotable'] ?? false) => 'notable',
                default => 'small',
            };

            $entry = [
                'id' => (int) $nodeId,
                'x' => round($node['x'], 1),
                'y' => round($node['y'], 1),
                'k' => $kind,
            ];

            if (isset($node['ascendancyId'])) {
                $entry['a'] = $node['ascendancyId'];
            }

            if ($kind === 'start') {
                $entry['ci'] = $node['classStartIndex'];
            } elseif (($node['name'] ?? '') !== '') {
                $entry['n'] = $node['name'];
            }

            if (($node['stats'] ?? []) !== []) {
                $entry['st'] = GameText::cleanLines($node['stats']);
            }

            $frame = $this->frameFor($frames, $node['icon'] ?? null, $kind);

            if ($frame !== null && $kind !== 'small') {
                $entry['s'] = $frame;
            }

            $nodes[] = $entry;
            $included[(int) $nodeId] = true;
        }

        $edges = [];
        $seen = [];

        foreach ($tree['nodes'] ?? [] as $nodeId => $node) {
            if (! isset($included[(int) $nodeId])) {
                continue;
            }

            // "out" lists each edge once, in one direction only.
            foreach ($node['out'] ?? [] as $target) {
                $target = (int) $target;

                if (! isset($included[$target])) {
                    continue;
                }

                $key = min((int) $nodeId, $target).':'.max((int) $nodeId, $target);

                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $edges[] = [(int) $nodeId, $target];
                }
            }
        }

        return [
            'bounds' => [
                'min_x' => $tree['min_x'] ?? 0,
                'min_y' => $tree['min_y'] ?? 0,
                'max_x' => $tree['max_x'] ?? 0,
                'max_y' => $tree['max_y'] ?? 0,
            ],
            'sheet' => [
                'w' => $sprite['meta']['size']['w'] ?? 0,
                'h' => $sprite['meta']['size']['h'] ?? 0,
            ],
            'classes' => array_values(array_map(fn (array $class) => $class['name'], $tree['classes'] ?? [])),
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /** @return array{0: int, 1: int, 2: int, 3: int}|null [x, y, w, h] in the sprite sheet */
    protected function frameFor(array $frames, ?string $iconPath, string $kind): ?array
    {
        if ($iconPath === null || $iconPath === '') {
            return null;
        }

        foreach (self::SPRITE_PREFIXES[$kind] ?? self::SPRITE_PREFIXES['default'] as $prefix) {
            $frame = $frames["{$prefix}:{$iconPath}"]['frame'] ?? null;

            if ($frame !== null) {
                return [$frame['x'], $frame['y'], $frame['w'], $frame['h']];
            }
        }

        return null;
    }
}
