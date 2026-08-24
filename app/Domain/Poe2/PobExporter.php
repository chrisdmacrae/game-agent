<?php

namespace App\Domain\Poe2;

use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\ItemBase;
use App\Models\Poe2\ItemMod;
use App\Models\Poe2\PassiveNode;
use App\Models\Poe2\UniqueItem;
use App\Models\SavedBuild;

/**
 * Exports a saved build as a Path of Building (PoE2 fork) import code:
 * XML -> zlib deflate -> URL-safe base64. The XML shape follows what
 * PathOfBuilding-PoE2 parses (Modules/Build.lua, Classes/PassiveSpec.lua,
 * Classes/SkillsTab.lua).
 */
class PobExporter
{
    public function __construct(protected Poe2Context $context) {}

    public function code(SavedBuild $build): string
    {
        return rtrim(strtr(base64_encode(gzcompress($this->xml($build), 9)), '+/', '-_'), '=');
    }

    public function xml(SavedBuild $build): string
    {
        $definition = $build->build;
        $versionId = $build->game_version_id ?? $this->context->versionId();

        $className = $definition['class'] ?? 'Scion';
        $ascendancyName = $definition['ascendancy'] ?? 'None';
        $level = $definition['level'] ?? 1;

        $classId = CharacterClass::forVersion($versionId)
            ->whereLike('name', $className)
            ->first()
            ?->raw['integer_id'] ?? 0;

        $ascendancy = Ascendancy::forVersion($versionId)
            ->whereLike('name', $ascendancyName)
            ->first();

        // Ascendancy keys are "{Class}{N}"; N is PoB's ascendClassId.
        $ascendClassId = $ascendancy !== null
            ? (int) (preg_replace('/\D/', '', $ascendancy->key) ?: 0)
            : 0;

        $treeVersion = $this->treeVersion($build);

        $nodeIds = array_map('intval', $definition['passives']['node_ids'] ?? []);

        foreach ($definition['passives']['ascendancy_nodes'] ?? [] as $name) {
            $nodeId = PassiveNode::forVersion($versionId)
                ->whereLike('name', $name)
                ->when($ascendancy, fn ($q) => $q->where('ascendancy_key', $ascendancy->key))
                ->value('node_id');

            if ($nodeId !== null) {
                $nodeIds[] = $nodeId;
            }
        }

        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('PathOfBuilding2');

        $xml->startElement('Build');
        $xml->writeAttribute('level', (string) $level);
        // PoB2's build-file format version (GameVersions.lua liveTargetVersion)
        // — NOT the tree version; any other value triggers a conversion popup.
        $xml->writeAttribute('targetVersion', '0_1');
        $xml->writeAttribute('className', $className);
        $xml->writeAttribute('ascendClassName', $ascendancyName);
        $xml->writeAttribute('mainSocketGroup', '1');
        $xml->writeAttribute('viewMode', 'TREE');
        $xml->endElement();

        $xml->startElement('Skills');
        $xml->writeAttribute('activeSkillSet', '1');
        $xml->writeAttribute('sortGemsByDPS', 'true');
        $xml->startElement('SkillSet');
        $xml->writeAttribute('id', '1');

        foreach ($definition['skills'] ?? [] as $setup) {
            $xml->startElement('Skill');
            $xml->writeAttribute('enabled', 'true');
            $xml->writeAttribute('label', '');

            foreach (array_merge([$setup['gem'] ?? null], $setup['supports'] ?? []) as $gemName) {
                if ($gemName === null) {
                    continue;
                }

                $xml->startElement('Gem');
                $xml->writeAttribute('nameSpec', $gemName);
                $xml->writeAttribute('enabled', 'true');
                $xml->writeAttribute('level', '20');
                $xml->writeAttribute('quality', '0');
                $xml->endElement();
            }

            $xml->endElement();
        }

        $xml->endElement(); // SkillSet
        $xml->endElement(); // Skills

        [$items, $slots, $jewelSockets] = $this->resolveItems($definition, $versionId);

        $xml->startElement('Tree');
        $xml->writeAttribute('activeSpec', '1');
        $xml->startElement('Spec');
        $xml->writeAttribute('title', 'Default');
        $xml->writeAttribute('treeVersion', $treeVersion);
        $xml->writeAttribute('classId', (string) $classId);
        $xml->writeAttribute('ascendClassId', (string) $ascendClassId);
        $xml->writeAttribute('nodes', implode(',', array_unique($nodeIds)));
        $xml->writeAttribute('masteryEffects', '');

        if ($jewelSockets !== []) {
            $xml->startElement('Sockets');

            foreach ($jewelSockets as $nodeId => $itemId) {
                $xml->startElement('Socket');
                $xml->writeAttribute('nodeId', (string) $nodeId);
                $xml->writeAttribute('itemId', (string) $itemId);
                $xml->endElement();
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();

        $this->writeItems($xml, $items, $slots);

        $xml->writeElement('Notes', trim(
            ($build->name ?? '')."\n\nExported from PoE2 Theorycrafter — ".$build->url(),
        ));
        $xml->startElement('Config');
        $xml->endElement();

        $xml->endElement(); // PathOfBuilding2

        return $xml->outputMemory();
    }

    /**
     * Build PoB item texts for all gear and jewels, resolving uniques and
     * bases against the database (base names, implicits, unique mod lines).
     *
     * @param  array<string, mixed>  $definition
     * @return array{0: array<int, string>, 1: array<string, int>, 2: array<int, int>} [items, slot=>itemId, jewelNodeId=>itemId]
     */
    protected function resolveItems(array $definition, int $versionId): array
    {
        $slotNames = [
            'helmet' => 'Helmet',
            'body' => 'Body Armour',
            'gloves' => 'Gloves',
            'boots' => 'Boots',
            'amulet' => 'Amulet',
            'ring1' => 'Ring 1',
            'ring2' => 'Ring 2',
            'belt' => 'Belt',
            'weapon1' => 'Weapon 1',
            'offhand1' => 'Weapon 2',
            'weapon2' => 'Weapon 1 Swap',
            'offhand2' => 'Weapon 2 Swap',
        ];

        $items = [];
        $slots = [];
        $jewelSockets = [];

        foreach ($definition['gear'] ?? [] as $item) {
            $itemId = count($items) + 1;
            $items[$itemId] = $this->itemText($item, $versionId);

            $slotName = $slotNames[$item['slot'] ?? ''] ?? null;

            if ($slotName !== null) {
                $slots[$slotName] = $itemId;
            }
        }

        foreach ($definition['jewels'] ?? [] as $jewel) {
            $itemId = count($items) + 1;
            $items[$itemId] = $this->itemText([
                'rarity' => $jewel['rarity'] ?? 'rare',
                'name' => $jewel['name'] ?? null,
                'mods' => $jewel['mods'] ?? [],
            ], $versionId);

            if (isset($jewel['socket_node_id'])) {
                $jewelSockets[(int) $jewel['socket_node_id']] = $itemId;
            }
        }

        return [$items, $slots, $jewelSockets];
    }

    /**
     * PoB raw item text: "Rarity: X" / name / base / "Implicits: N" /
     * implicit lines / explicit mod lines. Uniques resolve their base and
     * current-variant mods from the database; rare/magic bases contribute
     * their implicit lines.
     *
     * @param  array<string, mixed>  $item
     */
    protected function itemText(array $item, int $versionId): string
    {
        $rarity = strtolower($item['rarity'] ?? 'rare');
        $name = $item['name'] ?? null;
        $baseName = $item['base'] ?? null;
        $implicits = [];
        $mods = $item['mods'] ?? [];

        if ($rarity === 'unique' && $name !== null) {
            $unique = UniqueItem::forVersion($versionId)->whereLike('name', $name)->first();

            if ($unique !== null) {
                $baseName ??= $unique->base_name;

                $currentVariant = $unique->variants === [] ? null : count($unique->variants);
                $uniqueExplicits = [];

                foreach ($unique->mods as $mod) {
                    $applies = $mod['variants'] === null
                        || $currentVariant === null
                        || in_array($currentVariant, $mod['variants'], true);

                    if (! $applies) {
                        continue;
                    }

                    if ($mod['is_implicit'] ?? false) {
                        $implicits[] = $mod['text'];
                    } else {
                        $uniqueExplicits[] = $mod['text'];
                    }
                }

                // Prefer the database's mod lines over agent-provided ones.
                if ($uniqueExplicits !== []) {
                    $mods = $uniqueExplicits;
                }
            }
        } elseif ($baseName !== null) {
            $base = ItemBase::forVersion($versionId)
                ->whereLike('name', $baseName)
                ->whereIn('item_class', IconManifest::EQUIPMENT_CLASSES)
                ->first();

            if ($base !== null) {
                // Implicit mod text carries ranges and game markup ("[Chaos]");
                // resolve both so PoB parses the line.
                $implicits = ItemMod::forVersion($versionId)
                    ->whereIn('key', array_filter($base->implicits, 'is_string'))
                    ->pluck('text')
                    ->filter()
                    ->map(fn (string $text) => $this->resolveRanges((string) GameText::clean($text)))
                    ->all();
            }

            // Materialize loose stat priorities ("increased Cast Speed") into
            // concrete affix lines so PoB's parser counts them.
            $itemLevel = $item['item_level'] ?? 80;
            $mods = array_map(
                fn (string $line) => $this->materializeModLine($line, $base ?? null, $versionId, $itemLevel),
                $mods,
            );
        }

        $lines = ['Rarity: '.strtoupper($rarity)];

        // Rare/unique items have a name line then a base line; magic/normal
        // items have only the base line.
        if (in_array($rarity, ['unique', 'rare'], true)) {
            $lines[] = $name ?? ($baseName !== null ? "Theorycrafted {$baseName}" : 'Theorycrafted item');
        }

        if ($baseName !== null || $name === null) {
            $lines[] = $baseName ?? 'Unknown Base';
        }

        $lines[] = 'Implicits: '.count($implicits);

        return implode("\n", array_merge($lines, $implicits, $mods));
    }

    /**
     * @param  array<int, string>  $items
     * @param  array<string, int>  $slots
     */
    protected function writeItems(\XMLWriter $xml, array $items, array $slots): void
    {
        $xml->startElement('Items');
        $xml->writeAttribute('activeItemSet', '1');

        foreach ($items as $id => $text) {
            $xml->startElement('Item');
            $xml->writeAttribute('id', (string) $id);
            $xml->text("\n".$text."\n");
            $xml->endElement();
        }

        $xml->startElement('ItemSet');
        $xml->writeAttribute('id', '1');
        $xml->writeAttribute('useSecondWeaponSet', 'false');

        foreach ($slots as $slotName => $itemId) {
            $xml->startElement('Slot');
            $xml->writeAttribute('name', $slotName);
            $xml->writeAttribute('itemId', (string) $itemId);
            $xml->endElement();
        }

        $xml->endElement(); // ItemSet
        $xml->endElement(); // Items
    }

    /**
     * Turn a loose mod description into a concrete, PoB-parseable affix line
     * by matching it against the affix pool and taking the midpoint of the
     * best tier available at the given item level. Lines that already carry
     * numbers are kept verbatim; unmatched lines pass through unchanged.
     */
    protected function materializeModLine(string $line, ?ItemBase $base, int $versionId, int $itemLevel): string
    {
        if (preg_match('/\d/', $line)) {
            return $line;
        }

        $normalize = fn (string $text) => trim((string) preg_replace(
            '/\s+/',
            ' ',
            strtolower((string) preg_replace('/[()#+%.\d\-]+/', ' ', $text)),
        ));

        $wanted = $normalize($line);

        if ($wanted === '') {
            return $line;
        }

        $baseTags = $base?->tags ?? [];

        $keyPhrase = trim((string) preg_replace('/^(increased|reduced|added)\s+/i', '', trim($line)));

        $candidates = ItemMod::forVersion($versionId)
            ->where('domain', 'item')
            ->whereIn('generation_type', ['prefix', 'suffix'])
            ->whereLike('text', "%{$keyPhrase}%")
            ->get()
            ->filter(fn ($mod) => $mod->text !== null && $normalize($mod->text) === $wanted)
            ->filter(fn ($mod) => $baseTags === [] || array_intersect($mod->spawn_tags, $baseTags) !== []);

        if ($candidates->isEmpty()) {
            return $line;
        }

        // Best tier the item level allows; fall back to the lowest tier.
        $chosen = $candidates
            ->filter(fn ($mod) => $mod->required_level <= $itemLevel)
            ->sortByDesc('required_level')
            ->first() ?? $candidates->sortBy('required_level')->first();

        return $this->resolveRanges($chosen->text);
    }

    /** Replace "(a-b)" ranges with their midpoints: "+(80-89)" -> "+84.5". */
    protected function resolveRanges(string $text): string
    {
        return (string) preg_replace_callback(
            '/\((-?\d+(?:\.\d+)?)-(-?\d+(?:\.\d+)?)\)/',
            function (array $match) {
                $mid = ((float) $match[1] + (float) $match[2]) / 2;

                return rtrim(rtrim(number_format($mid, 1, '.', ''), '0'), '.');
            },
            $text,
        );
    }

    protected function treeVersion(SavedBuild $build): string
    {
        $version = $build->gameVersion?->version ?? $this->context->version()->version;

        // "0.5.2" -> "0_5", matching PoB2's TreeData directory names.
        if (preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
            return "{$matches[1]}_{$matches[2]}";
        }

        return '0_5';
    }
}
