<?php

namespace App\Domain\D4;

use App\Models\Build;
use App\Models\D4\Aspect;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;
use Illuminate\Support\Collection;

/**
 * Builds the hover-card entity dictionary for a saved Diablo IV build page:
 * every skill, aspect, unique, glyph and paragon notable referenced by the
 * build definition or mentioned in the guide text, with the accurate rendered
 * tooltip strings TooltipText produces and the icon atlas frame the importer
 * stored. Also wraps mentions in the rendered guide HTML with
 * <span data-entity="..."> markers, mirroring Poe2's BuildPageEnricher.
 */
class D4BuildPageEnricher
{
    public function __construct(
        protected D4Context $context,
        protected TooltipText $tooltips,
    ) {}

    /**
     * @return array{entities: array<string, array<string, mixed>>, guide_html: ?string}
     */
    public function enrich(Build $build, ?string $guideHtml): array
    {
        $definition = is_array($build->build) ? $build->build : [];
        $versionId = $build->game_version_id ?? $this->context->versionId();
        $guideText = $build->guide_markdown ?? '';

        $entities = collect();

        $this->addSkills($entities, $definition, $versionId, $guideText);
        $this->addAspects($entities, $definition, $versionId, $guideText);
        $this->addUniques($entities, $definition, $versionId, $guideText);
        $this->addGlyphs($entities, $definition, $versionId, $guideText);
        $this->addParagonNodes($entities, $definition, $versionId);

        return [
            'entities' => $entities->all(),
            'guide_html' => $guideHtml !== null
                ? $this->tagMentions($guideHtml, $entities->keys()->all())
                : null,
        ];
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $entities
     * @param  array<string, mixed>  $definition
     */
    protected function addSkills(Collection $entities, array $definition, int $versionId, string $guideText): void
    {
        $ranks = [];

        foreach ($definition['equipped_skills'] ?? [] as $setup) {
            if (is_array($setup) && is_string($setup['skill'] ?? null)) {
                $ranks[$setup['skill']] = is_numeric($setup['rank'] ?? null) ? (int) $setup['rank'] : null;
            }
        }

        foreach ($definition['skill_points'] ?? [] as $entry) {
            if (is_array($entry) && is_string($entry['skill'] ?? null)) {
                $ranks[$entry['skill']] ??= is_numeric($entry['points'] ?? null) ? (int) $entry['points'] : null;
            }
        }

        $skills = Skill::forVersion($versionId)
            ->whereIn('name', $this->wantedNames(
                collect(array_keys($ranks)),
                Skill::forVersion($versionId)->distinct()->pluck('name'),
                $guideText,
            ))
            ->orderByDesc('is_released')
            ->get()
            ->unique('name');

        foreach ($skills as $skill) {
            $rank = max(1, min($ranks[$skill->name] ?? 1, max($skill->max_rank, 1)));
            $values = TooltipText::scriptFormulaValues($skill->rank_values[$rank] ?? null);

            $entities[$skill->name] = [
                'kind' => 'skill',
                'name' => $skill->name,
                'category' => $skill->category,
                'class_name' => $skill->class_name,
                'rank' => $rank,
                'max_rank' => $skill->max_rank,
                'description' => $this->tooltips->render($skill->description, $values),
                'tags' => array_slice(is_array($skill->tags) ? $skill->tags : [], 0, 6),
                'icon' => $this->icon($skill->icon),
            ];
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $entities
     * @param  array<string, mixed>  $definition
     */
    protected function addAspects(Collection $entities, array $definition, int $versionId, string $guideText): void
    {
        $referenced = $this->gearItems($definition)
            ->pluck('aspect')
            ->filter(fn (mixed $name) => is_string($name) && $name !== '');

        $aspects = Aspect::forVersion($versionId)
            ->whereIn('name', $this->wantedNames(
                $referenced,
                Aspect::forVersion($versionId)->distinct()->pluck('name'),
                $guideText,
            ))
            ->orderByDesc('is_released')
            ->get()
            ->unique('name');

        foreach ($aspects as $aspect) {
            $entities[$aspect->name] ??= [
                'kind' => 'aspect',
                'name' => $aspect->name,
                'category' => $aspect->category,
                'description' => $aspect->display_text ?? $this->tooltips->render($aspect->text),
                'item_types' => array_slice(is_array($aspect->item_types) ? $aspect->item_types : [], 0, 6),
                'icon' => $this->icon($aspect->icon),
            ];
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $entities
     * @param  array<string, mixed>  $definition
     */
    protected function addUniques(Collection $entities, array $definition, int $versionId, string $guideText): void
    {
        $referenced = $this->gearItems($definition)
            ->filter(fn (array $item) => in_array($item['rarity'] ?? null, ['unique', 'mythic'], true))
            ->pluck('name')
            ->filter(fn (mixed $name) => is_string($name) && $name !== '');

        $uniques = UniqueItem::forVersion($versionId)
            ->whereIn('name', $this->wantedNames(
                $referenced,
                UniqueItem::forVersion($versionId)->pluck('name'),
                $guideText,
            ))
            ->orderByDesc('is_released')
            ->get()
            ->unique('name');

        foreach ($uniques as $unique) {
            $entities[$unique->name] ??= [
                'kind' => 'unique',
                'name' => $unique->name,
                'item_type' => $unique->item_type,
                'is_mythic' => $unique->is_mythic,
                'description' => $unique->display_text ?? $unique->power_text,
                'icon' => $this->icon($unique->icon),
            ];
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $entities
     * @param  array<string, mixed>  $definition
     */
    protected function addGlyphs(Collection $entities, array $definition, int $versionId, string $guideText): void
    {
        $referenced = collect($definition['paragon'] ?? [])
            ->pluck('glyph')
            ->filter(fn (mixed $name) => is_string($name) && $name !== '');

        $glyphs = ParagonGlyph::forVersion($versionId)
            ->whereIn('name', $this->wantedNames(
                $referenced,
                ParagonGlyph::forVersion($versionId)->distinct()->pluck('name'),
                $guideText,
            ))
            ->orderByDesc('is_released')
            ->get()
            ->unique('name');

        foreach ($glyphs as $glyph) {
            $effects = collect(is_array($glyph->effects) ? $glyph->effects : [])
                ->pluck('text')
                ->filter(fn (mixed $text) => is_string($text) && $text !== '')
                ->map(fn (string $text) => $this->tooltips->render($text) ?? $text)
                ->values()
                ->all();

            $entities[$glyph->name] ??= [
                'kind' => 'glyph',
                'name' => $glyph->name,
                'class_name' => $glyph->class_name,
                'effects' => $effects,
                'icon' => null,
            ];
        }
    }

    /**
     * Notables resolve against the grids of the boards the build attaches;
     * their tooltip is the attribute list on the cell.
     *
     * @param  Collection<string, array<string, mixed>>  $entities
     * @param  array<string, mixed>  $definition
     */
    protected function addParagonNodes(Collection $entities, array $definition, int $versionId): void
    {
        $paragon = collect($definition['paragon'] ?? [])->filter(fn (mixed $entry) => is_array($entry));

        $wanted = $paragon
            ->flatMap(fn (array $entry) => $entry['notables'] ?? [])
            ->filter(fn (mixed $name) => is_string($name) && $name !== '')
            ->unique();

        if ($wanted->isEmpty()) {
            return;
        }

        $boards = ParagonBoard::forVersion($versionId)
            ->whereIn('name', $paragon->pluck('board')->filter()->unique()->values()->all())
            ->get(['name', 'grid']);

        foreach ($boards as $board) {
            foreach (is_array($board->grid) ? $board->grid : [] as $row) {
                foreach ($row as $cell) {
                    $name = is_array($cell) ? ($cell['name'] ?? null) : null;

                    if (! is_string($name) || ! $wanted->contains($name) || isset($entities[$name])) {
                        continue;
                    }

                    $entities[$name] = [
                        'kind' => 'paragon-node',
                        'name' => $name,
                        'board' => $board->name,
                        'rarity' => $cell['rarity'] ?? null,
                        'attributes' => array_values(array_filter((array) ($cell['attributes'] ?? []), 'is_string')),
                        'icon' => $this->icon($cell['icon'] ?? null),
                    ];
                }
            }
        }
    }

    /**
     * The frontend icon payload: the extracted atlas sheet URL plus the
     * fractional crop rect. Null until the sheet's pixels are extracted, which
     * the UI renders as a letter badge.
     *
     * @return array{url: string, u0: float, v0: float, u1: float, v1: float, w: int|null, h: int|null}|null
     */
    protected function icon(mixed $icon): ?array
    {
        if (! is_array($icon)) {
            return null;
        }

        $url = IconManifest::atlasUrlFor($icon['texture'] ?? null);

        if ($url === null) {
            return null;
        }

        return [
            'url' => $url,
            'u0' => (float) ($icon['u0'] ?? 0),
            'v0' => (float) ($icon['v0'] ?? 0),
            'u1' => (float) ($icon['u1'] ?? 0),
            'v1' => (float) ($icon['v1'] ?? 0),
            // Crop pixel size — the only way to know the icon's aspect ratio,
            // since the UV fractions are relative to different sheet axes.
            'w' => is_numeric($icon['w'] ?? null) ? (int) $icon['w'] : null,
            'h' => is_numeric($icon['h'] ?? null) ? (int) $icon['h'] : null,
        ];
    }

    /**
     * Every equipped item in the keyed gear map plus the weapons list.
     *
     * @param  array<string, mixed>  $definition
     * @return Collection<int, array<string, mixed>>
     */
    protected function gearItems(array $definition): Collection
    {
        $gear = is_array($definition['gear'] ?? null) ? $definition['gear'] : [];
        $items = collect();

        foreach ($gear as $slot => $item) {
            if ($slot === 'weapons') {
                foreach (is_array($item) ? $item : [] as $weapon) {
                    if (is_array($weapon)) {
                        $items->push($weapon);
                    }
                }
            } elseif (is_array($item)) {
                $items->push($item);
            }
        }

        return $items;
    }

    /**
     * The names worth loading: everything the build references, plus any name
     * from the candidate pool that appears in the guide text. Case-sensitive,
     * because entity names are proper nouns.
     *
     * @param  Collection<int, string>  $referenced
     * @param  Collection<int, string>  $candidates
     * @return list<string>
     */
    protected function wantedNames(Collection $referenced, Collection $candidates, string $guideText): array
    {
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
}
