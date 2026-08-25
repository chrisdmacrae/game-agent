<?php

namespace App\Domain\Poe2;

use App\Domain\Builds\BuildPayload;
use App\Models\Build;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\Gem;
use App\Models\Poe2\ItemBase;
use App\Models\Poe2\ItemMod;
use App\Models\Poe2\PassiveNode;
use App\Models\Poe2\UniqueItem;
use Illuminate\Support\Collection;

/**
 * Builds the hover-card entity dictionary for a saved build page: every gem,
 * passive, and unique referenced by the build definition or mentioned in the
 * guide text, with enough detail to render a tooltip. Also wraps mentions in
 * the rendered guide HTML with <span data-entity="..."> markers.
 */
class BuildPageEnricher
{
    public function __construct(protected Poe2Context $context) {}

    /**
     * @return array{entities: array<string, array<string, mixed>>, guide_html: ?string}
     */
    public function enrich(Build $build, ?string $guideHtml): array
    {
        $definition = $build->build;

        $gemNames = collect($definition['skills'] ?? [])
            ->flatMap(fn (array $setup) => array_merge([$setup['gem'] ?? null], BuildPayload::supportNames($setup)))
            ->filter();

        $passiveNames = collect($definition['passives']['keystones'] ?? [])
            ->merge($definition['passives']['notables'] ?? []);

        $entities = collect();

        $versionId = $build->game_version_id ?? $this->context->versionId();

        // Referenced gems and passives, plus anything mentioned in the guide.
        $guideText = $build->guide_markdown ?? '';

        $gems = Gem::forVersion($versionId)
            ->whereIn('name', $this->wantedNames(
                $gemNames,
                Gem::forVersion($versionId)->distinct()->pluck('name'),
                $guideText,
            ))
            ->orderByDesc('is_released')
            ->get()
            ->unique('name');

        foreach ($gems as $gem) {
            $entities[$gem->name] = $this->gemEntity($gem);
        }

        $passiveQuery = fn () => PassiveNode::forVersion($versionId)->whereIn('kind', ['keystone', 'notable']);

        $passives = $passiveQuery()
            ->whereIn('name', $this->wantedNames(
                $passiveNames,
                $passiveQuery()->distinct()->pluck('name'),
                $guideText,
            ))
            ->get()
            ->unique('name');

        foreach ($passives as $node) {
            $entities[$node->name] ??= $this->passiveEntity($node);
        }

        $gearUniqueNames = collect($definition['gear'] ?? [])
            ->filter(fn (array $item) => ($item['rarity'] ?? null) === 'unique')
            ->pluck('name')
            ->merge(collect($definition['jewels'] ?? [])
                ->filter(fn (array $jewel) => ($jewel['rarity'] ?? null) === 'unique')
                ->pluck('name'))
            ->filter();

        $uniques = UniqueItem::forVersion($versionId)
            ->whereIn('name', $this->wantedNames(
                $gearUniqueNames,
                UniqueItem::forVersion($versionId)->pluck('name'),
                $guideText,
            ))
            ->get();

        foreach ($uniques as $unique) {
            $entities[$unique->name] ??= $this->uniqueEntity($unique);
        }

        $ascendancyPathIds = [];

        if (($definition['passives']['ascendancy_nodes'] ?? []) !== [] && isset($definition['ascendancy'])) {
            $ascendancy = Ascendancy::forVersion($versionId)
                ->whereLike('name', $definition['ascendancy'])
                ->first();

            if ($ascendancy !== null) {
                $targetIds = PassiveNode::forVersion($versionId)
                    ->whereIn('name', $definition['passives']['ascendancy_nodes'])
                    ->where('ascendancy_key', $ascendancy->key)
                    ->pluck('node_id')
                    ->all();

                $plan = new TreeGraph($this->context)->planAscendancy($ascendancy->key, $targetIds);

                if ($plan !== null) {
                    $ascendancyPathIds = array_merge(
                        [new TreeGraph($this->context)->ascendancyStartId($ascendancy->key)],
                        $plan['node_ids'],
                    );
                }
            }
        }

        return [
            'entities' => $entities->all(),
            'ascendancy_path_ids' => array_values(array_filter($ascendancyPathIds)),
            'gear_view' => $this->gearView($definition, $versionId),
            'guide_html' => $guideHtml !== null
                ? $this->tagMentions($guideHtml, $entities->keys()->all())
                : null,
        ];
    }

    /**
     * Resolves every gear entry and jewel against the item database for the
     * gear screen: icons (unique art or base art), implicits, cleaned mods.
     *
     * @param  array<string, mixed>  $definition
     * @return array{slots: array<string, array<string, mixed>>, jewels: list<array<string, mixed>>}
     */
    protected function gearView(array $definition, int $versionId): array
    {
        $slots = [];

        foreach ($definition['gear'] ?? [] as $item) {
            $slots[$item['slot'] ?? 'unknown'] = $this->gearItemView($item, $versionId);
        }

        $jewels = [];

        foreach ($definition['jewels'] ?? [] as $jewel) {
            $jewels[] = $this->gearItemView([
                'slot' => 'jewel',
                'rarity' => $jewel['rarity'] ?? 'rare',
                'name' => $jewel['name'] ?? null,
                'mods' => $jewel['mods'] ?? [],
            ], $versionId);
        }

        return ['slots' => $slots, 'jewels' => $jewels];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function gearItemView(array $item, int $versionId): array
    {
        $icon = null;
        $implicits = [];
        $uniqueMods = [];
        $baseName = $item['base'] ?? null;

        if (($item['rarity'] ?? null) === 'unique' && ! empty($item['name'])) {
            $unique = UniqueItem::forVersion($versionId)
                ->whereLike('name', $item['name'])
                ->first();

            if ($unique !== null) {
                $icon = IconManifest::iconUrlFor($unique->raw['dds'] ?? null);
                $baseName ??= $unique->base_name;
                $uniqueMods = $this->uniqueEntity($unique)['mods'];
            }
        }

        if ($baseName !== null) {
            $base = ItemBase::forVersion($versionId)
                ->whereLike('name', $baseName)
                ->whereIn('item_class', IconManifest::EQUIPMENT_CLASSES)
                ->first();

            if ($base !== null) {
                $icon ??= IconManifest::iconUrlFor($base->raw['visual_identity']['dds_file'] ?? null);

                // Implicits are mod keys; resolve them to display text.
                $implicits = GameText::cleanLines(
                    ItemMod::forVersion($versionId)
                        ->whereIn('key', array_filter($base->implicits, 'is_string'))
                        ->pluck('text')
                        ->filter()
                        ->all(),
                );
            }
        }

        return [
            'slot' => $item['slot'] ?? null,
            'rarity' => $item['rarity'] ?? 'rare',
            'name' => $item['name'] ?? null,
            'base' => $baseName,
            'icon' => $icon,
            'implicits' => $implicits,
            'mods' => $uniqueMods !== [] ? $uniqueMods : GameText::cleanLines($item['mods'] ?? []),
            // One entry per rune socket, empty ones normalised to null so the
            // gear screen can draw a dashed "empty socket" chip for them.
            'runes' => array_map(
                fn (mixed $rune) => is_string($rune) && $rune !== '' ? $rune : null,
                array_values($item['runes'] ?? []),
            ),
            'instill' => $item['instill'] ?? null,
        ];
    }

    /**
     * The names worth loading: everything the build references, plus any name
     * from the candidate pool that appears in the guide text.
     *
     * @param  Collection<int, string>  $referenced
     * @param  Collection<int, string>  $candidates
     * @return list<string>
     */
    protected function wantedNames(Collection $referenced, Collection $candidates, string $guideText): array
    {
        // Case-sensitive: entity names are proper nouns, and case-insensitive
        // matching turns ordinary words ("void", "mobility") into gem links.
        $mentioned = trim($guideText) === ''
            ? collect()
            : $candidates->filter(fn (?string $name) => $name !== null
                && mb_strlen($name) > 3
                && mb_strpos($guideText, $name) !== false);

        return $referenced->merge($mentioned)->unique()->values()->all();
    }

    /**
     * Wrap plain-text mentions of known entity names in the rendered HTML with
     * hoverable spans. Only text outside of tags is touched.
     *
     * @param  list<string>  $names
     */
    protected function tagMentions(string $html, array $names): string
    {
        if ($names === []) {
            return $html;
        }

        // Longest names first so "Cast on Dodge" wins over a shorter overlap.
        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $pattern = '/\b(?:'.implode('|', array_map(
            fn (string $name) => preg_quote($name, '/'),
            $names,
        )).')\b/u';

        $segments = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '<')) {
                $result .= $segment;

                continue;
            }

            $result .= preg_replace_callback(
                $pattern,
                fn (array $match) => '<span class="entity-ref" data-entity="'.e($match[0]).'">'.$match[0].'</span>',
                $segment,
            );
        }

        return $result;
    }

    /** @return array<string, mixed> */
    protected function gemEntity(Gem $gem): array
    {
        $statTexts = collect($gem->skill_details)
            ->flatMap(function (array $skill) {
                $set = $skill['stat_sets'][0] ?? [];
                $perLevel = $set['per_level'] ?? [];
                $levels = array_map('intval', array_keys($perLevel));
                $top = $levels === [] ? null : max(array_filter($levels, fn ($l) => $l <= 20) ?: $levels);

                return array_merge(
                    $top !== null ? array_values($perLevel[(string) $top]['stat_text'] ?? []) : [],
                    array_values($set['static']['stat_text'] ?? []),
                );
            })
            ->unique()
            ->take(6)
            ->values()
            ->all();

        $spirit = collect($gem->skill_details)->pluck('static.reservations.spirit')->filter()->first();

        return [
            'kind' => $gem->gem_type === 'support' ? 'support' : 'gem',
            'name' => $gem->name,
            'color' => $gem->color,
            'description' => GameText::clean($gem->description),
            'tags' => array_slice($gem->tags, 0, 6),
            'spirit_reservation' => $spirit,
            'icon' => IconManifest::iconUrlFor($gem->raw['icon_dds_file'] ?? null),
            'stat_text' => GameText::cleanLines($statTexts),
        ];
    }

    /** @return array<string, mixed> */
    protected function passiveEntity(PassiveNode $node): array
    {
        return [
            'kind' => 'passive',
            'name' => $node->name,
            'passive_kind' => $node->kind,
            'stats' => GameText::cleanLines($node->stats),
            'sprite' => $this->spriteFor($node->raw['icon'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    protected function uniqueEntity(UniqueItem $unique): array
    {
        $currentVariant = $unique->variants === [] ? null : count($unique->variants);

        $mods = collect($unique->mods)
            ->filter(fn (array $mod) => $mod['variants'] === null
                || $currentVariant === null
                || in_array($currentVariant, $mod['variants'], true))
            ->map(fn (array $mod) => ($mod['is_implicit'] ? '(implicit) ' : '').GameText::clean($mod['text']))
            ->values()
            ->all();

        return [
            'kind' => 'unique',
            'name' => $unique->name,
            'base_name' => $unique->base_name,
            'item_class' => $unique->item_class,
            'icon' => IconManifest::iconUrlFor($unique->raw['dds'] ?? null),
            'mods' => $mods,
        ];
    }

    /** @return array{x: int, y: int, w: int, h: int}|null */
    protected function spriteFor(?string $iconPath): ?array
    {
        if ($iconPath === null) {
            return null;
        }

        static $frames = null;

        if ($frames === null) {
            $spritePath = public_path('games/poe2/tree/skills.json');

            $frames = is_file($spritePath)
                ? (json_decode((string) file_get_contents($spritePath), true)['frames'] ?? [])
                : [];
        }

        foreach (['keystoneActive', 'notableActive', 'normalActive'] as $prefix) {
            $frame = $frames["{$prefix}:{$iconPath}"]['frame'] ?? null;

            if ($frame !== null) {
                return ['x' => $frame['x'], 'y' => $frame['y'], 'w' => $frame['w'], 'h' => $frame['h']];
            }
        }

        return null;
    }
}
