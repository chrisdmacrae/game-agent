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
            $prefix.'resistances' => 'nullable|array',
            $prefix.'resistances.fire' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.cold' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.lightning' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.chaos' => 'nullable|integer|min:-200|max:90',
            $prefix.'content_tier' => 'nullable|string|in:campaign,early_endgame,endgame,pinnacle',
        ];
    }
}
