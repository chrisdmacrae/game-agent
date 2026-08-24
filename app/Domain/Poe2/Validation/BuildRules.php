<?php

namespace App\Domain\Poe2\Validation;

/**
 * The request-validation rules for a structured build definition, shared by
 * the validate_build and save_build MCP tools.
 */
class BuildRules
{
    /** @return array<string, string> */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'class' => 'nullable|string|max:50',
            $prefix.'ascendancy' => 'nullable|string|max:50',
            $prefix.'level' => 'nullable|integer|min:1|max:100',
            $prefix.'skills' => 'required|array|min:1',
            $prefix.'skills.*.gem' => 'required|string|max:100',
            $prefix.'skills.*.supports' => 'nullable|array',
            $prefix.'skills.*.supports.*' => 'string|max:100',
            $prefix.'spirit_available' => 'nullable|integer|min:0|max:1000',
            $prefix.'passives' => 'nullable|array',
            $prefix.'passives.keystones' => 'nullable|array',
            $prefix.'passives.keystones.*' => 'string|max:100',
            $prefix.'passives.notables' => 'nullable|array',
            $prefix.'passives.notables.*' => 'string|max:100',
            $prefix.'passives.points_used' => 'nullable|integer|min:0|max:200',
            $prefix.'passives.ascendancy_nodes' => 'nullable|array|max:20',
            $prefix.'passives.ascendancy_nodes.*' => 'string|max:100',
            $prefix.'passives.node_ids' => 'nullable|array|max:250',
            $prefix.'passives.node_ids.*' => 'integer|min:0',
            $prefix.'passives.granted_nodes' => 'nullable|array|max:30',
            $prefix.'passives.granted_nodes.*.node_id' => 'required|integer|min:0',
            $prefix.'passives.granted_nodes.*.source' => 'required|string|in:instilled_amulet,unique_jewel,ascendancy_mechanic',
            $prefix.'passives.granted_nodes.*.detail' => 'nullable|string|max:200',
            $prefix.'gear' => 'nullable|array|max:14',
            $prefix.'gear.*.slot' => 'required|string|in:helmet,body,gloves,boots,amulet,ring1,ring2,belt,weapon1,offhand1,weapon2,offhand2',
            $prefix.'gear.*.rarity' => 'required|string|in:unique,rare,magic,normal',
            $prefix.'gear.*.name' => 'nullable|string|max:100',
            $prefix.'gear.*.base' => 'nullable|string|max:100',
            $prefix.'gear.*.mods' => 'nullable|array|max:8',
            $prefix.'gear.*.mods.*' => 'string|max:150',
            $prefix.'gear.*.instill' => 'nullable|array',
            $prefix.'gear.*.instill.notable' => 'required_with:'.$prefix.'gear.*.instill|string|max:100',
            $prefix.'gear.*.instill.emotions' => 'nullable|array|max:3',
            $prefix.'gear.*.instill.emotions.*' => 'string|max:60',
            $prefix.'jewels' => 'nullable|array|max:12',
            $prefix.'jewels.*.name' => 'required|string|max:100',
            $prefix.'jewels.*.rarity' => 'required|string|in:unique,rare,magic',
            $prefix.'jewels.*.socket_node_id' => 'nullable|integer|min:0',
            $prefix.'jewels.*.mods' => 'nullable|array|max:6',
            $prefix.'jewels.*.mods.*' => 'string|max:150',
            $prefix.'resistances' => 'nullable|array',
            $prefix.'resistances.fire' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.cold' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.lightning' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.chaos' => 'nullable|integer|min:-200|max:90',
            $prefix.'content_tier' => 'nullable|string|in:campaign,early_endgame,endgame,pinnacle',
        ];
    }
}
