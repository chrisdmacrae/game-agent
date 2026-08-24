<?php

namespace App\Domain\Poe2;

use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;
use App\Models\Poe2\PassiveNode;
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

        $xml->startElement('Tree');
        $xml->writeAttribute('activeSpec', '1');
        $xml->startElement('Spec');
        $xml->writeAttribute('title', 'Default');
        $xml->writeAttribute('treeVersion', $treeVersion);
        $xml->writeAttribute('classId', (string) $classId);
        $xml->writeAttribute('ascendClassId', (string) $ascendClassId);
        $xml->writeAttribute('nodes', implode(',', array_unique($nodeIds)));
        $xml->writeAttribute('masteryEffects', '');
        $xml->endElement();
        $xml->endElement();

        $this->writeItems($xml, $definition);

        $xml->writeElement('Notes', trim(
            ($build->name ?? '')."\n\nExported from PoE2 Theorycrafter — ".$build->url(),
        ));
        $xml->startElement('Config');
        $xml->endElement();

        $xml->endElement(); // PathOfBuilding2

        return $xml->outputMemory();
    }

    /** @param array<string, mixed> $definition */
    protected function writeItems(\XMLWriter $xml, array $definition): void
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

        foreach ($definition['gear'] ?? [] as $item) {
            $rarity = strtoupper($item['rarity'] ?? 'RARE');
            $name = $item['name'] ?? ucfirst($item['rarity'] ?? 'rare').' item';

            $lines = ["Rarity: {$rarity}", $name];

            if (! empty($item['base'])) {
                $lines[] = $item['base'];
            }

            foreach ($item['mods'] ?? [] as $mod) {
                $lines[] = $mod;
            }

            $itemId = count($items) + 1;
            $items[$itemId] = implode("\n", $lines);

            $slotName = $slotNames[$item['slot'] ?? ''] ?? null;

            if ($slotName !== null) {
                $slots[$slotName] = $itemId;
            }
        }

        foreach ($definition['jewels'] ?? [] as $jewel) {
            $rarity = strtoupper($jewel['rarity'] ?? 'RARE');
            $lines = ["Rarity: {$rarity}", $jewel['name']];

            foreach ($jewel['mods'] ?? [] as $mod) {
                $lines[] = $mod;
            }

            $items[count($items) + 1] = implode("\n", $lines);
        }

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
