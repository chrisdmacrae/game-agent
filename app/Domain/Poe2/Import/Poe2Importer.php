<?php

namespace App\Domain\Poe2\Import;

use App\Domain\Poe2\IconManifest;
use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\TreeRender;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\Gem;
use App\Models\Poe2\ItemBase;
use App\Models\Poe2\ItemMod;
use App\Models\Poe2\PassiveNode;
use App\Models\Poe2\StatTranslation;
use App\Models\Poe2\UniqueItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Poe2Importer
{
    /** @var array<string, int> */
    public array $counts = [];

    public function __construct(
        protected DataSourceClient $client,
        protected UniqueTextParser $uniqueParser,
    ) {}

    /**
     * Import all PoE2 data for the given patch version. Creates (or replaces
     * the data of) a GameVersion and marks it active on success.
     */
    public function import(string $version, ?string $league = null): GameVersion
    {
        $game = Game::firstOrCreate(['slug' => 'poe2'], ['name' => config('games.poe2.name')]);

        $gameVersion = GameVersion::updateOrCreate(
            ['game_id' => $game->id, 'version' => $version],
            ['league' => $league],
        );

        $skills = $this->client->repoeJson('skills');

        $this->importClasses($gameVersion, $this->client->repoeJson('characters'));
        $this->importAscendancies($gameVersion, $this->client->repoeJson('ascendancies'));
        $this->importGems($gameVersion, $this->client->repoeJson('skill_gems'), $skills);
        $this->importItemBases($gameVersion, $this->client->repoeJson('base_items'));
        $this->importMods($gameVersion, $this->client->repoeJson('mods'));
        $this->importStatTranslations($gameVersion, $this->client->repoeJson('stat_translations/stat_descriptions'));
        $this->importTree($gameVersion, $this->client->treeJson());
        $this->importUniques($gameVersion);
        $this->publishTreeAssets();

        $gameVersion->update([
            'fingerprint' => $this->client->fingerprint(),
            'imported_at' => now(),
            'is_active' => true,
        ]);

        GameVersion::where('game_id', $game->id)
            ->whereKeyNot($gameVersion->id)
            ->update(['is_active' => false]);

        $this->counts['icon_manifest'] = new IconManifest(new Poe2Context)->write();

        return $gameVersion;
    }

    /** @param array<int|string, mixed> $characters */
    protected function importClasses(GameVersion $gameVersion, array $characters): void
    {
        $rows = [];

        foreach ($characters as $character) {
            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'metadata_id' => $character['metadata_id'],
                'name' => $character['name'],
                'description' => $character['description'] ?? null,
                'base_stats' => json_encode($character['base_stats'] ?? []),
                'raw' => json_encode($character),
            ];
        }

        $this->counts['classes'] = $this->replace(CharacterClass::class, $gameVersion, $rows, ['metadata_id']);
    }

    /** @param array<string, mixed> $ascendancies */
    protected function importAscendancies(GameVersion $gameVersion, array $ascendancies): void
    {
        $rows = [];

        foreach ($ascendancies as $key => $ascendancy) {
            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'key' => (string) $key,
                'name' => $ascendancy['name'] ?? (string) $key,
                'class_name' => preg_replace('/\d+$/', '', (string) $key) ?: null,
                'flavour_text' => $ascendancy['flavour_text'] ?? null,
                'raw' => json_encode([
                    'class_number' => $ascendancy['class_number'] ?? null,
                    'disabled' => $ascendancy['disabled'] ?? false,
                ]),
            ];
        }

        $this->counts['ascendancies'] = $this->replace(Ascendancy::class, $gameVersion, $rows, ['key']);
    }

    /**
     * Gems come from two files: skill_gems.json (the gem items) and
     * skills.json (the skills they grant, including per-level stats).
     *
     * @param  array<string, mixed>  $gems
     * @param  array<string, mixed>  $skills
     */
    protected function importGems(GameVersion $gameVersion, array $gems, array $skills): void
    {
        $rows = [];

        foreach ($gems as $metadataId => $gem) {
            $name = $gem['base_item']['display_name'] ?? null;

            if ($name === null) {
                continue; // Internal-only gems without an item form.
            }

            $skillDetails = [];
            $description = null;

            foreach ($gem['grants_skills'] ?? [] as $skillKey) {
                $skill = $skills[$skillKey] ?? null;

                if ($skill === null) {
                    continue;
                }

                $description ??= trim($skill['active_skill']['description'] ?? '') ?: null;

                $skillDetails[] = [
                    'key' => $skillKey,
                    'display_name' => $skill['active_skill']['display_name'] ?? null,
                    'description' => trim($skill['active_skill']['description'] ?? '') ?: null,
                    'types' => $skill['active_skill']['types'] ?? [],
                    'weapon_restrictions' => $skill['active_skill']['weapon_restrictions'] ?? [],
                    'cast_time' => $skill['cast_time'] ?? null,
                    'is_support' => $skill['is_support'] ?? false,
                    'support_gem' => $skill['support_gem'] ?? null,
                    'static' => $skill['static'] ?? null,
                    'stat_sets' => $skill['stat_sets'] ?? [],
                ];
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'metadata_id' => (string) $metadataId,
                'name' => $name,
                'gem_type' => $gem['gem_type'] ?? 'active',
                'color' => $gem['color'] ?? null,
                'is_released' => ($gem['base_item']['release_state'] ?? null) === 'released',
                'description' => $description,
                'tags' => json_encode($gem['tags'] ?? []),
                'requirement_weights' => json_encode($gem['requirement_weights'] ?? []),
                'recommended_supports' => json_encode($gem['recommended_supports'] ?? []),
                'granted_skills' => json_encode($gem['grants_skills'] ?? []),
                'skill_details' => json_encode($skillDetails),
                'raw' => json_encode($gem),
            ];
        }

        $this->counts['gems'] = $this->replace(Gem::class, $gameVersion, $rows, ['metadata_id']);
    }

    /** @param array<string, mixed> $bases */
    protected function importItemBases(GameVersion $gameVersion, array $bases): void
    {
        $rows = [];

        foreach ($bases as $metadataId => $base) {
            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'metadata_id' => (string) $metadataId,
                'name' => $base['name'] ?? (string) $metadataId,
                'item_class' => $base['item_class'] ?? 'Unknown',
                'domain' => $base['domain'] ?? null,
                'drop_level' => (int) ($base['drop_level'] ?? 0),
                'release_state' => $base['release_state'] ?? null,
                'implicits' => json_encode($base['implicits'] ?? []),
                'requirements' => json_encode($base['requirements'] ?? []),
                'tags' => json_encode($base['tags'] ?? []),
                'properties' => json_encode($base['properties'] ?? []),
                'raw' => json_encode($base),
            ];
        }

        $this->counts['item_bases'] = $this->replace(ItemBase::class, $gameVersion, $rows, ['metadata_id']);
    }

    /** @param array<string, mixed> $mods */
    protected function importMods(GameVersion $gameVersion, array $mods): void
    {
        $rows = [];

        foreach ($mods as $key => $mod) {
            $spawnTags = [];

            foreach ($mod['spawn_weights'] ?? [] as $weight) {
                if (($weight['weight'] ?? 0) > 0) {
                    $spawnTags[] = $weight['tag'];
                }
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'key' => (string) $key,
                'name' => $mod['name'] ?? null,
                'domain' => $mod['domain'] ?? 'unknown',
                'generation_type' => $mod['generation_type'] ?? 'unknown',
                'group_type' => $mod['type'] ?? null,
                'required_level' => (int) ($mod['required_level'] ?? 0),
                'is_essence_only' => (bool) ($mod['is_essence_only'] ?? false),
                'text' => $mod['text'] ?? null,
                'groups' => json_encode($mod['groups'] ?? []),
                'spawn_tags' => json_encode($spawnTags),
                'spawn_weights' => json_encode($mod['spawn_weights'] ?? []),
                'stats' => json_encode($mod['stats'] ?? []),
                'raw' => json_encode([
                    'adds_tags' => $mod['adds_tags'] ?? [],
                    'implicit_tags' => $mod['implicit_tags'] ?? [],
                    'grants_effects' => $mod['grants_effects'] ?? [],
                ]),
            ];
        }

        $this->counts['mods'] = $this->replace(ItemMod::class, $gameVersion, $rows, ['key']);
    }

    /** @param list<array<string, mixed>> $translations */
    protected function importStatTranslations(GameVersion $gameVersion, array $translations): void
    {
        $rows = [];

        foreach ($translations as $translation) {
            $ids = $translation['ids'] ?? [];

            if ($ids === []) {
                continue;
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'primary_stat_id' => mb_substr($ids[0], 0, 512),
                'stat_ids' => json_encode($ids),
                'translations' => json_encode($translation['English'] ?? []),
            ];
        }

        StatTranslation::where('game_version_id', $gameVersion->id)->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            StatTranslation::insert(array_map(fn ($row) => $row + [
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk));
        }

        $this->counts['stat_translations'] = count($rows);
    }

    /** @param array<string, mixed> $tree */
    protected function importTree(GameVersion $gameVersion, array $tree): void
    {
        $rows = [];

        foreach ($tree['nodes'] ?? [] as $nodeId => $node) {
            if (! is_numeric($nodeId)) {
                continue;
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'node_id' => (int) $nodeId,
                'name' => $node['name'] ?? null,
                'kind' => $this->nodeKind($node),
                'ascendancy_key' => $node['ascendancyId'] ?? null,
                'stats' => json_encode($node['stats'] ?? []),
                'connections' => json_encode($node['out'] ?? []),
                'raw' => json_encode($node),
            ];
        }

        $this->counts['passive_nodes'] = $this->replace(PassiveNode::class, $gameVersion, $rows, ['node_id']);
    }

    /** @param array<string, mixed> $node */
    protected function nodeKind(array $node): string
    {
        return match (true) {
            isset($node['classStartIndex']) => 'class_start',
            ($node['isKeystone'] ?? false) => 'keystone',
            ($node['isJewelSocket'] ?? false) => 'jewel_socket',
            ($node['isNotable'] ?? false) => 'notable',
            ($node['isAscendancyStart'] ?? false) => 'ascendancy_start',
            default => 'small',
        };
    }

    protected function importUniques(GameVersion $gameVersion): void
    {
        $rows = [];

        // repoe's uniques export carries the art file for each unique.
        $ddsByName = collect($this->client->repoeJson('uniques'))
            ->mapWithKeys(fn (array $unique) => [
                $unique['name'] ?? '' => $unique['visual_identity']['dds_file'] ?? null,
            ]);

        foreach (config('games.poe2.pob_uniques_files') as $file) {
            $lua = $this->client->pobUniquesLua($file);

            foreach ($this->uniqueParser->blocksFromLua($lua) as $block) {
                $parsed = $this->uniqueParser->parseBlock($block);

                if ($parsed === null) {
                    continue;
                }

                $rows[$parsed['name']] = [
                    'game_version_id' => $gameVersion->id,
                    'name' => $parsed['name'],
                    'base_name' => $parsed['base_name'],
                    'item_class' => $this->itemClassForBase($gameVersion, $parsed['base_name']),
                    'implicit_count' => $parsed['implicit_count'],
                    'variants' => json_encode($parsed['variants']),
                    'mods' => json_encode($parsed['mods']),
                    'source_text' => $parsed['source_text'],
                    'raw' => json_encode([
                        'source_file' => $file,
                        'dds' => $ddsByName[$parsed['name']] ?? null,
                    ]),
                ];
            }
        }

        $this->counts['uniques'] = $this->replace(UniqueItem::class, $gameVersion, array_values($rows), ['name']);
    }

    /**
     * Publish the official passive tree sprite sheet (icon images + frame
     * coordinates) so build pages can render passive icons locally.
     */
    protected function publishTreeAssets(): void
    {
        $directory = public_path('games/poe2/tree');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach (['skills.webp', 'skills.json'] as $file) {
            file_put_contents("{$directory}/{$file}", $this->client->treeAsset($file));
        }

        $sprite = json_decode($this->client->treeAsset('skills.json'), true) ?: [];

        $render = new TreeRender()->build($this->client->treeJson(), $sprite);

        file_put_contents("{$directory}/render.json", json_encode($render));

        $this->counts['tree_assets'] = 3;
        $this->counts['tree_render_nodes'] = count($render['nodes']);
    }

    /** @var array<string, string>|null name => item_class lookup */
    protected ?array $baseClassLookup = null;

    protected function itemClassForBase(GameVersion $gameVersion, string $baseName): ?string
    {
        $this->baseClassLookup ??= ItemBase::forVersion($gameVersion->id)
            ->pluck('item_class', 'name')
            ->all();

        return $this->baseClassLookup[$baseName] ?? null;
    }

    /**
     * Replace a version's rows for a model: upsert in chunks, then prune rows
     * no longer present in the source data.
     *
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     */
    protected function replace(string $model, GameVersion $gameVersion, array $rows, array $uniqueBy): int
    {
        DB::transaction(function () use ($model, $gameVersion, $rows, $uniqueBy) {
            foreach (array_chunk($rows, 250) as $chunk) {
                $model::upsert($chunk, array_merge(['game_version_id'], $uniqueBy));
            }

            $keyColumn = $uniqueBy[0];

            $model::where('game_version_id', $gameVersion->id)
                ->whereNotIn($keyColumn, array_column($rows, $keyColumn))
                ->delete();
        });

        return count($rows);
    }
}
