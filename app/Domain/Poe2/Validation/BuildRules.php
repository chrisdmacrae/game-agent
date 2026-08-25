<?php

namespace App\Domain\Poe2\Validation;

use App\Domain\Builds\BuildStage;
use Closure;

/**
 * The request-validation rules for a structured build definition, shared by
 * the validate_build and save_build MCP tools.
 *
 * These rules and SaveBuildTool::schema() describe the same payload and must
 * be changed together. Everything except `skills` is optional: the MCP writes
 * a partial build and a human finishes it in the web editor.
 */
class BuildRules
{
    /** @var list<string> */
    public const TIERS = ['S', 'A', 'B', 'C'];

    /**
     * The largest number of rune sockets any item base carries. Anything above
     * this is a data-entry mistake rather than a build.
     */
    public const MAX_RUNE_SOCKETS = 8;

    /** @return array<string, mixed> */
    public static function rules(string $prefix = ''): array
    {
        return array_merge(
            self::identityRules($prefix),
            self::skillRules($prefix),
            self::passiveRules($prefix),
            self::gearRules($prefix),
            self::presentationRules($prefix),
        );
    }

    /** @return array<string, mixed> */
    protected static function identityRules(string $prefix): array
    {
        return [
            $prefix.'class' => 'nullable|string|max:50',
            $prefix.'ascendancy' => 'nullable|string|max:50',
            $prefix.'level' => 'nullable|integer|min:1|max:100',
            $prefix.'spirit_available' => 'nullable|integer|min:0|max:1000',
            $prefix.'resistances' => 'nullable|array',
            $prefix.'resistances.fire' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.cold' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.lightning' => 'nullable|integer|min:-100|max:90',
            $prefix.'resistances.chaos' => 'nullable|integer|min:-200|max:90',
            $prefix.'content_tier' => 'nullable|string|in:campaign,early_endgame,endgame,pinnacle',
            $prefix.'stage' => 'nullable|string|in:'.implode(',', BuildStage::values()),
            $prefix.'tier' => 'nullable|string|in:'.implode(',', self::TIERS),
            $prefix.'dps' => 'nullable|integer|min:0',
            $prefix.'ehp' => 'nullable|integer|min:0',
            $prefix.'cost_divine' => 'nullable|numeric|min:0|max:99999999',
            $prefix.'hardcore_viable' => 'nullable|boolean',
        ];
    }

    /** @return array<string, mixed> */
    protected static function skillRules(string $prefix): array
    {
        return [
            $prefix.'skills' => 'required|array|min:1',
            $prefix.'skills.*.gem' => 'required|string|max:100',
            $prefix.'skills.*.role' => 'nullable|string|max:60',
            $prefix.'skills.*.level' => 'nullable|integer|min:1|max:40',
            $prefix.'skills.*.quality' => 'nullable|integer|min:0|max:100',
            $prefix.'skills.*.cost' => 'nullable|string|max:60',
            $prefix.'skills.*.tags' => 'nullable|array|max:6',
            $prefix.'skills.*.tags.*' => 'string|max:40',
            $prefix.'skills.*.reported' => 'nullable|string|max:300',
            // Not capped at 5 here: BuildValidator reports the socket limit as
            // a game-rule violation with an explanation instead of a 422.
            $prefix.'skills.*.supports' => 'nullable|array|max:20',
            $prefix.'skills.*.supports.*' => ['nullable', self::supportEntryRule()],
        ];
    }

    /** @return array<string, mixed> */
    protected static function passiveRules(string $prefix): array
    {
        return [
            $prefix.'passives' => 'nullable|array',
            $prefix.'passives.keystones' => 'nullable|array',
            $prefix.'passives.keystones.*' => 'string|max:100',
            $prefix.'passives.notables' => 'nullable|array',
            $prefix.'passives.notables.*' => 'string|max:100',
            $prefix.'passives.points_used' => 'nullable|integer|min:0|max:200',
            // Pasted by a human out of the in-game planner; the web editor is
            // the only writer that ever fills this in.
            $prefix.'passives.import_string' => 'nullable|string|max:8000',
            $prefix.'passives.ascendancy_nodes' => 'nullable|array|max:20',
            $prefix.'passives.ascendancy_nodes.*' => 'string|max:100',
            $prefix.'passives.node_ids' => 'nullable|array|max:250',
            $prefix.'passives.node_ids.*' => 'integer|min:0',
            $prefix.'passives.granted_nodes' => 'nullable|array|max:30',
            $prefix.'passives.granted_nodes.*.node_id' => 'required|integer|min:0',
            $prefix.'passives.granted_nodes.*.source' => 'required|string|in:instilled_amulet,unique_jewel,ascendancy_mechanic',
            $prefix.'passives.granted_nodes.*.detail' => 'nullable|string|max:200',
        ];
    }

    /** @return array<string, mixed> */
    protected static function gearRules(string $prefix): array
    {
        return [
            $prefix.'gear' => 'nullable|array|max:14',
            $prefix.'gear.*.slot' => 'required|string|in:helmet,body,gloves,boots,amulet,ring1,ring2,belt,weapon1,offhand1,weapon2,offhand2',
            $prefix.'gear.*.rarity' => 'required|string|in:unique,rare,magic,normal',
            $prefix.'gear.*.name' => 'nullable|string|max:100',
            $prefix.'gear.*.base' => 'nullable|string|max:100',
            $prefix.'gear.*.mods' => 'nullable|array|max:8',
            $prefix.'gear.*.mods.*' => 'string|max:150',
            // A null or empty entry is an empty socket, so items are nullable.
            $prefix.'gear.*.runes' => 'nullable|array|max:'.self::MAX_RUNE_SOCKETS,
            $prefix.'gear.*.runes.*' => 'nullable|string|max:100',
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
            $prefix.'charms' => 'nullable|array|max:3',
            $prefix.'charms.*.name' => 'required|string|max:100',
            $prefix.'charms.*.note' => 'nullable|string|max:200',
            $prefix.'flasks' => 'nullable|array|max:2',
            $prefix.'flasks.*.name' => 'required|string|max:100',
            $prefix.'flasks.*.note' => 'nullable|string|max:200',
        ];
    }

    /** @return array<string, mixed> */
    protected static function presentationRules(string $prefix): array
    {
        return [
            $prefix.'milestones' => 'nullable|array|max:12',
            $prefix.'milestones.*.level' => 'required|integer|min:1|max:100',
            $prefix.'milestones.*.text' => 'required|string|max:300',
            $prefix.'stats' => 'nullable|array',
            $prefix.'stats.offence' => 'nullable|array|max:8',
            $prefix.'stats.offence.*.label' => 'required|string|max:60',
            $prefix.'stats.offence.*.value' => 'required|string|max:60',
            $prefix.'stats.defence' => 'nullable|array|max:8',
            $prefix.'stats.defence.*.label' => 'required|string|max:60',
            $prefix.'stats.defence.*.value' => 'required|string|max:60',
            $prefix.'how_it_plays' => 'nullable|array|max:3',
            $prefix.'how_it_plays.*' => 'string|max:300',
            $prefix.'works_because' => 'nullable|array|max:4',
            $prefix.'works_because.*' => 'string|max:300',
            $prefix.'watch_out_for' => 'nullable|array|max:4',
            $prefix.'watch_out_for.*' => 'string|max:300',
        ];
    }

    /**
     * A support entry is either a gem name or {name, effect}. Both are stored
     * as objects; see BuildPayload::normalize().
     */
    protected static function supportEntryRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value)) {
                if (mb_strlen($value) > 100) {
                    $fail("The {$attribute} support gem name may not be longer than 100 characters.");
                }

                return;
            }

            if (! is_array($value)) {
                $fail("The {$attribute} must be a support gem name or an object with a name and an optional effect.");

                return;
            }

            $name = $value['name'] ?? null;

            if (! is_string($name) || $name === '' || mb_strlen($name) > 100) {
                $fail("The {$attribute} object needs a name of 1-100 characters.");
            }

            $effect = $value['effect'] ?? null;

            if ($effect !== null && (! is_string($effect) || mb_strlen($effect) > 200)) {
                $fail("The {$attribute} effect must be a string of at most 200 characters.");
            }
        };
    }
}
