<?php

namespace App\Domain\D4\Import;

use App\Domain\D4\D4Context;
use App\Domain\D4\IconManifest;
use App\Domain\D4\TooltipText;
use App\Models\D4\Affix;
use App\Models\D4\Aspect;
use App\Models\D4\CalcTable;
use App\Models\D4\CharacterClass;
use App\Models\D4\ItemType;
use App\Models\D4\ParagonBoard;
use App\Models\D4\ParagonGlyph;
use App\Models\D4\Skill;
use App\Models\D4\UniqueItem;
use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transforms the DiabloTools/d4data asset dump into the d4_* tables.
 *
 * Each dataset walks one SNO group's directory in the acquired source tree,
 * resolves the cross references it needs (SnoRefs), joins the localised text
 * (StringResolver), flags unreleased/scratch content (ContentFilter) and lands
 * through the same transactional upsert-then-prune pass the PoE 2 importer
 * uses, so a re-run is idempotent and a failed run leaves the previous version
 * active.
 */
class D4Importer
{
    /** @var array<string, int> */
    public array $counts = [];

    protected const DIR_PLAYER_CLASS = 'json/base/meta/PlayerClass';

    protected const DIR_POWER = 'json/base/meta/Power';

    protected const DIR_PARAGON_BOARD = 'json/base/meta/ParagonBoard';

    protected const DIR_PARAGON_GLYPH = 'json/base/meta/ParagonGlyph';

    protected const DIR_AFFIX = 'json/base/meta/Affix';

    protected const DIR_ASPECT = 'json/base/meta/Aspect';

    protected const DIR_ITEM = 'json/base/meta/Item';

    protected const DIR_ITEM_TYPE = 'json/base/meta/ItemType';

    protected const FILE_POWER_FORMULA_TABLES = 'json/base/meta/GameBalance/PowerFormulaTables.gam.json';

    protected const FILE_ATTRIBUTE_FORMULAS = 'json/base/meta/GameBalance/AttributeFormulas.gam.json';

    protected const FILE_LEVEL_SCALING = 'json/base/meta/GameBalance/LevelScaling.gam.json';

    protected const FILE_GLOBALS = 'json/base/meta/Global/globals.glo.json';

    protected const FILE_ATTRIBUTES = 'attributes.json';

    /**
     * The AttributeFormulas rows that give a weapon's damage roll per item
     * power, one per attack-speed class.
     *
     * @var array<string, string>
     */
    protected const WEAPON_DAMAGE_FORMULAS = [
        'slow' => 'GearAffix_Slow_Weapon_Damage',
        'normal' => 'GearAffix_Normal_Weapon_Damage',
        'fast' => 'GearAffix_Fast_Weapon_Damage',
        'very_fast' => 'GearAffix_VeryFast_Weapon_Damage',
    ];

    /**
     * `eContributingCoreStat` in PlayerClass `arCoreStatBenefit`. Derived
     * empirically like the class mask order; the dump ships no name map.
     *
     * @var array<int, string>
     */
    protected const CORE_STATS = [
        0 => 'strength',
        1 => 'intelligence',
        2 => 'willpower',
        3 => 'dexterity',
    ];

    /**
     * The globals.glo.json fields the calculator (and paragon planner) read.
     * An allowlist, because the file is half a megabyte of engine knobs.
     *
     * @var list<string>
     */
    protected const GLOBAL_FIELDS = [
        'flPlayerCritDamageScalar',
        'nParagonPointsEarnedPerLevel',
        'arGlyphRadiusLevels',
        'arAffixCountPerQuality',
        'flItemUpgradeAttributeBonus',
        'arItemUpgradeArmorPowerLevels',
        'arItemUpgradeWeaponPowerLevels',
        'arItemUpgradeJewelryPowerLevels',
        'arParagonPowerBudgetMultiplier',
    ];

    /**
     * Values are rounded before they land in jsonb. The dump's floats are
     * single precision widened to double — a formula table holds
     * 1.100000023841858 for 1.1 — so anything multiplied through one of them
     * would otherwise be stored, and rendered, with that noise attached.
     */
    protected const VALUE_PRECISION = 6;

    /**
     * The order of every 8-wide class mask in the dump (`fUsableByClass`,
     * `fAllowedForPlayerClass`, `arUsableByClass`). Derived empirically; the
     * dump ships no enum name map. Doubles as the hardcoded display-name
     * roster, since PlayerClass has no StringList.
     *
     * @var array<int, string>
     */
    protected const CLASS_NAMES = [
        0 => 'Sorcerer',
        1 => 'Druid',
        2 => 'Barbarian',
        3 => 'Rogue',
        4 => 'Necromancer',
        5 => 'Spiritborn',
        6 => 'Paladin',
        7 => 'Warlock',
    ];

    /**
     * `tPrimaryResource.eType` has no published name map either, so the primary
     * resource is keyed off the class instead. Paladin and Warlock are absent
     * because they were unreleased in the sampled build.
     *
     * @var array<string, string>
     */
    protected const CLASS_RESOURCES = [
        'Barbarian' => 'fury',
        'Druid' => 'spirit',
        'Necromancer' => 'essence',
        'Rogue' => 'energy',
        'Sorcerer' => 'mana',
        'Spiritborn' => 'vigor',
    ];

    /** @var array<int, string> `ParagonNodeDefinition.eRarityOverride` */
    protected const NODE_RARITIES = [
        0 => 'normal',
        2 => 'magic',
        3 => 'rare',
        4 => 'legendary',
    ];

    /** @var array<int, string> `AffixDefinition.eMagicType` */
    protected const MAGIC_TYPES = [
        0 => 'stat',
        1 => 'power',
        2 => 'unique_power',
    ];

    protected StringResolver $strings;

    protected SnoRefs $refs;

    protected ContentFilter $filter;

    /** @var array<string, string> ParagonBoard name => class name */
    protected array $boardClasses = [];

    /** @var array<string, array<string, mixed>|null> ParagonNode name => resolved grid cell */
    protected array $paragonNodes = [];

    /** @var array<int, list<string>>|null item label id => item type display names */
    protected ?array $itemTypeLabels = null;

    /** @var array<string, string>|null ItemType SNO name => display name */
    protected ?array $itemTypeNames = null;

    /** @var array<int, int>|null Power SNO id => max talent ranks */
    protected ?array $skillMaxRanks = null;

    protected ?FormulaEvaluator $evaluator = null;

    protected ?TooltipText $tooltips = null;

    protected ?TextureFrames $textureFrames = null;

    public function __construct(
        protected D4DataSource $source,
    ) {
        $this->strings = new StringResolver($source);
        $this->refs = new SnoRefs($source);
        $this->filter = new ContentFilter;
    }

    /**
     * Import every dataset for a build of the game and mark it active. Without
     * an explicit label the dump's own buildVersion.txt names the version.
     */
    public function import(?string $version = null): GameVersion
    {
        $game = Game::firstOrCreate(['slug' => 'diablo-4'], ['name' => config('games.diablo-4.name')]);

        $gameVersion = GameVersion::updateOrCreate([
            'game_id' => $game->id,
            'version' => $version !== null && $version !== '' ? $version : $this->source->buildVersion(),
        ]);

        $this->importClasses($gameVersion);
        $this->importSkills($gameVersion);
        $this->importParagonBoards($gameVersion);
        $this->importGlyphs($gameVersion);
        $this->importAffixes($gameVersion);
        $this->importAspects($gameVersion);
        $this->importUniques($gameVersion);
        $this->importItemTypes($gameVersion);
        $this->importCalcTables($gameVersion);

        $gameVersion->update([
            'fingerprint' => $this->source->fingerprint(),
            'imported_at' => now(),
            'is_active' => true,
        ]);

        GameVersion::where('game_id', $game->id)
            ->whereKeyNot($gameVersion->id)
            ->update(['is_active' => false]);

        $this->counts['icon_manifest'] = new IconManifest(new D4Context)->write();

        return $gameVersion;
    }

    /**
     * PlayerClass files carry no text of their own; the SNO name is the display
     * name. The paragon board roster is captured here because a board only
     * knows its class through the class that lists it.
     */
    protected function importClasses(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_PLAYER_CLASS, 'pcl') as $key => $definition) {
            $snoId = $this->snoId($definition);

            if ($snoId === null) {
                continue;
            }

            foreach (SnoRefs::names($definition['arAvailableParagonBoards'] ?? []) as $board) {
                $this->boardClasses[$board] = $key;
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $key,
                'resource' => self::CLASS_RESOURCES[$key] ?? null,
                'description' => null,
                'is_released' => $this->filter->isReleased($key, $definition),
                'raw' => json_encode([
                    'file' => $definition['__fileName__'] ?? null,
                    'skill_kit' => SnoRefs::name($definition['snoSkillKit'] ?? null),
                    'default_basic_attack' => SnoRefs::name($definition['snoDefaultBasicAttack'] ?? null),
                    'paragon_boards' => SnoRefs::names($definition['arAvailableParagonBoards'] ?? []),
                    'primary_resource_type' => $definition['tPrimaryResource']['eType'] ?? null,
                    'secondary_resource_type' => $definition['tSecondaryResource']['eType'] ?? null,
                ]),
            ];
        }

        $this->counts['classes'] = $this->replace(CharacterClass::class, $gameVersion, $rows, ['sno_id']);
    }

    /**
     * Powers are the whole game's ability system — monster attacks, systems
     * glue and player skills all live in one directory. `snoClassRequirement`
     * is the only reliable player-skill gate, so a power without one is not a
     * skill and is skipped rather than imported and flagged.
     */
    protected function importSkills(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_POWER, 'pow') as $key => $definition) {
            $className = SnoRefs::name($definition['snoClassRequirement'] ?? null);
            $snoId = $this->snoId($definition);

            if ($className === null || $snoId === null) {
                continue;
            }

            $strings = $this->strings->labelsFor('Power', $key);
            $primaryTag = SnoRefs::name($definition['tPrimaryTag']['gbidSkillTag'] ?? null);
            $maxRank = $this->skillMaxRanks()[$snoId] ?? 0;
            $formulas = $this->scriptFormulas($definition);

            $tags = [];
            $searchTags = [];

            foreach ($definition['arSkillTags'] ?? [] as $tag) {
                $name = SnoRefs::name($tag['gbidSkillTag'] ?? null);

                if ($name === null) {
                    continue;
                }

                if (($tag['bSearchOnly'] ?? false) === true) {
                    $searchTags[] = $name;
                } else {
                    $tags[] = $name;
                }
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $strings['name'] ?? $key,
                'class_name' => $className,
                'category' => $this->skillCategory($primaryTag),
                'max_rank' => $maxRank,
                'description' => $strings['desc'] ?? null,
                'tags' => json_encode($tags),
                'enhancements' => json_encode($this->skillEnhancements($definition, $strings)),
                'formulas' => json_encode($formulas, JSON_FORCE_OBJECT),
                'rank_values' => json_encode($this->skillRankValues($formulas, $maxRank), JSON_FORCE_OBJECT),
                'icon' => $this->encodedIcon($definition['hIconNormal'] ?? null),
                'is_released' => $this->filter->isReleased($key, $definition, honourVisibleInUi: true),
                'raw' => json_encode([
                    'key' => $key,
                    'file' => $definition['__fileName__'] ?? null,
                    'primary_tag' => $primaryTag,
                    'search_tags' => $searchTags,
                    'rankup_description' => $strings['rankup_desc'] ?? null,
                    'is_passive' => (bool) ($definition['bIsPassive'] ?? false),
                    'is_basic_attack' => (bool) ($definition['bIsBasicAttack'] ?? false),
                    'is_channelled' => (bool) ($definition['bChannelled'] ?? false),
                    'must_be_learned' => (bool) ($definition['bMustBeLearned'] ?? false),
                    'visible_in_ui' => (bool) ($definition['bVisibleInUI'] ?? false),
                    'class_relative_category' => $definition['eSkillCat'] ?? null,
                    'weapon_requirement' => SnoRefs::name($definition['snoSkillRequirement'] ?? null),
                    'linked_passives' => SnoRefs::names($definition['arLinkedPassivePowers'] ?? []),
                    'cooldown' => $this->formulaValue($definition['tCooldownTime'] ?? null),
                    'lucky_hit_chance' => $this->formulaValue($definition['tCombatEffectChance'] ?? null),
                    'resource_costs' => $this->resourceCosts($definition),
                    'damage' => $this->damagePayload($definition),
                ]),
            ];
        }

        $this->counts['skills'] = $this->replace(Skill::class, $gameVersion, $rows, ['sno_id']);
    }

    /**
     * The skill's primary damage payload, for the stat calculator: the
     * weapon-damage coefficient (an `SF_n` reference into the stored
     * formulas, or a literal), the class base-damage scalar index and the
     * variance. `eType 0` is the weapon-damage-percent case — near universal
     * for player skills. The first payload with a non-zero coefficient is the
     * primary hit.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<string, mixed>|null
     */
    protected function damagePayload(array $definition): ?array
    {
        foreach ($definition['arPayloads'] ?? [] as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $damage = $payload['tDamage'] ?? null;
            $scalar = is_array($damage) ? $this->formulaValue($damage['tHitpointScalar'] ?? null) : null;

            if ($scalar === null || $scalar === '0') {
                continue;
            }

            return [
                'type' => $damage['eType'] ?? null,
                'scalar' => $scalar,
                'flat_level' => $this->formulaValue($damage['tFlatLevel'] ?? null),
                'class_scalar_index' => $payload['eClassBaseDamageScalar'] ?? null,
                'attack_speed' => $this->formulaValue($payload['tAttackSpeed'] ?? null),
                'variance' => $this->formulaValue($payload['tDamageVariance'] ?? null),
            ];
        }

        return null;
    }

    /**
     * Boards are a flat row-major `nWidth * nWidth` array of ParagonNode
     * references; `nWidth` varies per board, so it is never assumed.
     */
    protected function importParagonBoards(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_PARAGON_BOARD, 'pbd') as $key => $definition) {
            $snoId = $this->snoId($definition);

            if ($snoId === null) {
                continue;
            }

            $width = (int) ($definition['nWidth'] ?? 0);

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $this->strings->labelFor('ParagonBoard', $key, 'name') ?? $key,
                'class_name' => $this->boardClasses[$key] ?? null,
                'grid' => json_encode($this->boardGrid($width, $definition['arEntries'] ?? [])),
                'is_released' => $this->filter->isReleased($key, $definition),
                'raw' => json_encode([
                    'key' => $key,
                    'file' => $definition['__fileName__'] ?? null,
                    'width' => $width,
                    'filled_cells' => count(array_filter((array) ($definition['arEntries'] ?? []))),
                    'legendary_node_icon' => $this->textureFrames()->resolve($definition['legendaryNodeIcon'] ?? null),
                ]),
            ];
        }

        $this->counts['paragon_boards'] = $this->replace(ParagonBoard::class, $gameVersion, $rows, ['sno_id']);
    }

    protected function importGlyphs(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_PARAGON_GLYPH, 'gph') as $key => $definition) {
            $snoId = $this->snoId($definition);

            if ($snoId === null) {
                continue;
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $this->strings->labelFor('ParagonGlyph', $key, 'name') ?? $key,
                'class_name' => $this->singleClass($definition['fUsableByClass'] ?? null),
                'effects' => json_encode($this->glyphEffects($definition)),
                'is_released' => $this->filter->isReleased($key, $definition),
                'raw' => json_encode([
                    'key' => $key,
                    'file' => $definition['__fileName__'] ?? null,
                    'rarity' => $definition['eRarity'] ?? null,
                    'affixes' => SnoRefs::names($definition['arAffixes'] ?? []),
                ]),
            ];
        }

        $this->counts['paragon_glyphs'] = $this->replace(ParagonGlyph::class, $gameVersion, $rows, ['sno_id']);
    }

    /**
     * One definition type covers stat affixes, tempered affixes, legendary
     * aspect powers and unique item powers, so the row is keyed by the file
     * basename rather than by an SNO id the table does not carry.
     */
    protected function importAffixes(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_AFFIX, 'aff') as $key => $definition) {
            $strings = $this->strings->labelsFor('Affix', $key);
            $text = $this->affixText($definition, $strings);

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'key' => mb_substr($key, 0, 512),
                'name' => $strings['name'] ?? null,
                'magic_type' => self::MAGIC_TYPES[(int) ($definition['eMagicType'] ?? -1)] ?? null,
                'text' => $text,
                'display_text' => $this->affixDisplayText($text, $definition),
                'item_types' => json_encode($this->itemTypesForLabels($definition['arAllowedItemLabels'] ?? [])),
                'class_name' => $this->singleClass($definition['fAllowedForPlayerClass'] ?? null),
                'is_tempering' => ($definition['bIsTemperedAffix'] ?? false) === true,
                'temper_family' => $this->temperFamily($key, $definition),
                'value_range' => json_encode($this->affixValueRange($definition)),
                'is_released' => $this->filter->isReleased($key, $definition),
                'raw' => json_encode($this->affixRaw($definition, $strings)),
            ];
        }

        $this->counts['affixes'] = $this->replace(Affix::class, $gameVersion, $rows, ['key']);
    }

    /**
     * An Aspect file is a two-field pointer at an Affix; every scrap of content
     * — name, text, item restrictions, numbers — comes from that affix, which
     * is also where the codex category tag lives.
     */
    protected function importAspects(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_ASPECT, 'asp') as $key => $definition) {
            $snoId = $this->snoId($definition);

            if ($snoId === null) {
                continue;
            }

            $affixRef = $definition['snoAffix'] ?? null;
            $affixKey = SnoRefs::name($affixRef);
            $affix = $this->optionalJson(SnoRefs::path($affixRef)) ?? [];
            $affixStrings = $affixKey !== null ? $this->strings->labelsFor('Affix', $affixKey) : [];
            $text = $this->affixText($affix, $affixStrings);

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $affixStrings['name'] ?? $key,
                'category' => $this->aspectCategory($affix),
                'text' => $text,
                'display_text' => $this->affixDisplayText($text, $affix),
                'item_types' => json_encode($this->itemTypesForLabels($affix['arAllowedItemLabels'] ?? [])),
                'value_range' => json_encode($this->affixValueRange($affix)),
                'icon' => $this->encodedIcon($definition['hIconOverride'] ?? null),
                'is_released' => $this->filter->isReleased($key, $definition)
                    && ($affixKey === null || $this->filter->isReleased($affixKey, $affix)),
                'raw' => json_encode([
                    'key' => $key,
                    'file' => $definition['__fileName__'] ?? null,
                    'affix' => $affixKey,
                    'codex_description' => $affixStrings['codexdesc'] ?? null,
                    'passive_power' => SnoRefs::name($affix['snoPassivePower'] ?? null),
                    'static_values' => $affix['arStaticValues'] ?? [],
                    'class_name' => $this->singleClass($affix['fAllowedForPlayerClass'] ?? null),
                ]),
            ];
        }

        $this->counts['aspects'] = $this->replace(Aspect::class, $gameVersion, $rows, ['sno_id']);
    }

    /**
     * Uniques are Item files with `eMagicType == 2`; their fixed powers are the
     * affixes in `arForcedAffixes`.
     */
    protected function importUniques(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_ITEM, 'itm') as $key => $definition) {
            $snoId = $this->snoId($definition);

            if ($snoId === null || (int) ($definition['eMagicType'] ?? 0) !== 2) {
                continue;
            }

            $strings = $this->strings->labelsFor('Item', $key);
            $name = $strings['name'] ?? $key;
            $affixes = $this->forcedAffixes($definition);
            $powerText = implode("\n", array_filter(array_column($affixes, 'text')));
            $displayText = implode("\n", array_filter(array_column($affixes, 'display_text')));
            $itemTypeKey = SnoRefs::name($definition['snoItemType'] ?? null);

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $name,
                'item_type' => $itemTypeKey === null ? null : ($this->itemTypeNames()[$itemTypeKey] ?? $itemTypeKey),
                'class_name' => $this->singleClass($definition['fUsableByClass'] ?? null),
                'is_mythic' => $this->isMythic($key, $name, $definition),
                'affixes' => json_encode($affixes),
                'power_text' => $powerText !== '' ? $powerText : null,
                'display_text' => $displayText !== '' ? $displayText : null,
                'icon' => $this->encodedIcon($this->itemIconHandle($definition)),
                'is_released' => $this->filter->isReleased($key, $definition),
                'raw' => json_encode([
                    'key' => $key,
                    'file' => $definition['__fileName__'] ?? null,
                    'item_type' => $itemTypeKey,
                    'base_item' => SnoRefs::name($definition['snoBaseItem'] ?? null),
                    'item_families' => SnoRefs::names($definition['arItemFamilies'] ?? []),
                    'inherent_affixes' => SnoRefs::names($definition['arInherentAffixes'] ?? []),
                    'flavor' => $strings['flavor'] ?? null,
                    'transmog_name' => $strings['transmogname'] ?? null,
                ]),
            ];
        }

        $this->counts['uniques'] = $this->replace(UniqueItem::class, $gameVersion, $rows, ['sno_id']);
    }

    protected function importItemTypes(GameVersion $gameVersion): void
    {
        $rows = [];

        foreach ($this->definitions(self::DIR_ITEM_TYPE, 'itt') as $key => $definition) {
            $snoId = $this->snoId($definition);

            if ($snoId === null) {
                continue;
            }

            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'sno_id' => $snoId,
                'name' => $this->strings->labelFor('ItemType', $key, 'name') ?? $key,
                'slot' => $this->itemTypeSlot($definition),
                'implicits' => json_encode($this->itemTypeImplicits($definition)),
                'is_released' => $this->filter->isReleased($key, $definition),
                'raw' => json_encode([
                    'key' => $key,
                    'file' => $definition['__fileName__'] ?? null,
                    'body_slots' => $definition['arBodySlots'] ?? [],
                    'item_labels' => $definition['arItemLabels'] ?? [],
                    'weapon_class' => $definition['eWeaponClass'] ?? null,
                    'pack_slot' => $definition['ePackSlot'] ?? null,
                    'usable_by_class' => $this->classNames($definition['fUsableByClass'] ?? null),
                ]),
            ];
        }

        $this->counts['item_types'] = $this->replace(ItemType::class, $gameVersion, $rows, ['sno_id']);
    }

    /**
     * Walk one SNO group's directory, yielding the object name (file basename
     * without its extensions) and the decoded definition. Files that fail to
     * decode at all are the only ones dropped.
     *
     * @return iterable<string, array<array-key, mixed>>
     */
    protected function definitions(string $directory, string $extension): iterable
    {
        $suffix = ".{$extension}.json";

        foreach ($this->source->files($directory) as $file) {
            if (! str_ends_with($file, $suffix)) {
                continue;
            }

            try {
                $definition = $this->source->json($directory.'/'.$file);
            } catch (RuntimeException) {
                continue;
            }

            yield mb_substr($file, 0, -mb_strlen($suffix)) => $definition;
        }
    }

    /**
     * @return array<array-key, mixed>|null
     */
    protected function optionalJson(?string $path): ?array
    {
        if ($path === null) {
            return null;
        }

        try {
            return $this->source->optionalJson($path);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    protected function snoId(array $definition): ?int
    {
        $snoId = $definition['__snoID__'] ?? null;

        return is_numeric($snoId) ? (int) $snoId : null;
    }

    /**
     * The skill-tree category. `eSkillCat` is class-relative and useless across
     * classes; the primary skill tag (`Skill_Primary_Core`) is not.
     */
    protected function skillCategory(?string $primaryTag): ?string
    {
        if ($primaryTag === null) {
            return null;
        }

        $category = str_starts_with($primaryTag, 'Skill_Primary_')
            ? mb_substr($primaryTag, mb_strlen('Skill_Primary_'))
            : $primaryTag;

        return $category !== '' ? mb_strtolower($category) : null;
    }

    /**
     * Power SNO id => the rank cap its skill-tree unlock node declares.
     *
     * @return array<int, int>
     */
    protected function skillMaxRanks(): array
    {
        if ($this->skillMaxRanks !== null) {
            return $this->skillMaxRanks;
        }

        $ranks = [];

        foreach ($this->refs->rowsForType(43) as $row) {
            $powerId = SnoRefs::id($row['snoPower'] ?? null);
            $maxRanks = $row['dwMaxTalentRanks'] ?? null;

            if ((int) ($row['eType'] ?? -1) === 0 && $powerId !== null && is_numeric($maxRanks)) {
                $ranks[$powerId] = (int) $maxRanks;
            }
        }

        return $this->skillMaxRanks = $ranks;
    }

    /**
     * The skill-tree upgrade nodes hanging off a power. Their text lives in the
     * power's own StringList under `Mod<dwModId>_Name` / `_Description`.
     *
     * @param  array<array-key, mixed>  $definition
     * @param  array<string, string>  $strings
     * @return list<array<string, mixed>>
     */
    protected function skillEnhancements(array $definition, array $strings): array
    {
        $enhancements = [];

        foreach ($definition['arMods'] ?? [] as $mod) {
            $modId = $mod['dwModId'] ?? null;

            if (! is_array($mod) || ! is_numeric($modId)) {
                continue;
            }

            $modId = (int) $modId;

            $enhancements[] = [
                'mod_id' => $modId,
                'name' => $strings[mb_strtolower("Mod{$modId}_Name")] ?? null,
                'description' => $strings[mb_strtolower("Mod{$modId}_Description")] ?? null,
                'max_points' => (int) ($mod['nMaxPoints'] ?? 0),
                'mod_type' => $mod['eModType'] ?? null,
                'adds_tags' => SnoRefs::names($mod['arSkillTagsToAdd'] ?? []),
            ];
        }

        return $enhancements;
    }

    /**
     * A power's script formulas, keyed by their position in
     * `ptScriptFormulas` — that position *is* the `SF_12` token's number, and
     * blank entries are kept out of the map rather than stored as empty
     * strings. This is the one part of the power definition the row's `raw`
     * payload deliberately drops (fifty formulas per power, mostly noise), so
     * the map lands in its own column instead.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<int, string>
     */
    protected function scriptFormulas(array $definition): array
    {
        $formulas = [];

        foreach (array_values((array) ($definition['ptScriptFormulas'] ?? [])) as $index => $entry) {
            $formula = is_array($entry) ? $this->formulaValue($entry['tFormula'] ?? null) : null;

            if ($formula !== null) {
                $formulas[$index] = $formula;
            }
        }

        return $formulas;
    }

    /**
     * Evaluate every script formula at every rank the skill can reach, so the
     * numbers a tooltip needs are computed once here rather than per request.
     *
     * `sLevel` is the skill's rank and indexes the formula tables directly, so
     * ranks run 1..max_rank (a skill with no rank-up node still gets rank 1).
     * Formulas that do not evaluate — anything reading player state, another
     * power's tuning or a legendary rank — are simply absent, which is what
     * keeps their tokens visible as tokens in the rendered text.
     *
     * @param  array<int, string>  $formulas
     * @return array<int, array<int, float|array{min: float, max: float}>>
     */
    protected function skillRankValues(array $formulas, int $maxRank): array
    {
        if ($formulas === []) {
            return [];
        }

        $values = [];

        for ($rank = 1; $rank <= max($maxRank, 1); $rank++) {
            $atRank = [];

            foreach ($formulas as $index => $formula) {
                $interval = $this->evaluator()->evaluate($formula, ['sLevel' => $rank], $formulas);

                if ($interval !== null) {
                    $atRank[$index] = $this->compactInterval($interval);
                }
            }

            if ($atRank !== []) {
                $values[$rank] = $atRank;
            }
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    protected function resourceCosts(array $definition): array
    {
        $costs = [];

        foreach ($definition['arResourceCosts'] ?? [] as $cost) {
            if (! is_array($cost)) {
                continue;
            }

            $costs[] = [
                'resource_type' => $cost['eType'] ?? null,
                'initial' => $this->formulaValue($cost['tInitialCost'] ?? null),
                'channelling' => $this->formulaValue($cost['tChannellingCost'] ?? null),
                'minimum_required' => $this->formulaValue($cost['tMinRequired'] ?? null),
            ];
        }

        return $costs;
    }

    /**
     * Expand a board's flat entry list into rows of resolved cells. Nodes
     * repeat across a board and across boards, so each is resolved once.
     *
     * @param  array<array-key, mixed>  $entries
     * @return list<list<array<string, mixed>|null>>
     */
    protected function boardGrid(int $width, array $entries): array
    {
        if ($width <= 0) {
            return [];
        }

        $entries = array_values($entries);
        $grid = [];

        for ($y = 0; $y < $width; $y++) {
            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $row[] = $this->boardCell($entries[($y * $width) + $x] ?? null);
            }

            $grid[] = $row;
        }

        return $grid;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function boardCell(mixed $ref): ?array
    {
        $key = SnoRefs::name($ref);

        if ($key === null) {
            return null;
        }

        return $this->paragonNodes[$key] ??= $this->paragonNode($key, $ref);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paragonNode(string $key, mixed $ref): array
    {
        $node = $this->optionalJson(SnoRefs::path($ref)) ?? [];
        $rarity = $node['eRarityOverride'] ?? null;

        return [
            'key' => $key,
            'sno_id' => SnoRefs::id($ref),
            'name' => $this->strings->labelFor('ParagonNode', $key, 'name') ?? $key,
            'rarity' => is_numeric($rarity) ? (self::NODE_RARITIES[(int) $rarity] ?? null) : null,
            'has_socket' => ($node['bHasSocket'] ?? false) === true,
            'is_gate' => ($node['bIsGate'] ?? false) === true,
            'passive_power' => SnoRefs::name($node['snoPassivePower'] ?? null),
            'attributes' => $this->attributeNames($node['ptAttributes'] ?? []),
            'icon' => $this->textureFrames()->resolve($node['hIconMask'] ?? null),
        ];
    }

    /**
     * A glyph's three affixes: the stat bonus, the unique power and the
     * legendary-node bonus. Their text is on the ParagonGlyphAffix itself.
     *
     * @param  array<array-key, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    protected function glyphEffects(array $definition): array
    {
        $effects = [];

        foreach ($definition['arAffixes'] ?? [] as $ref) {
            $key = SnoRefs::name($ref);

            if ($key === null) {
                continue;
            }

            $affix = $this->optionalJson(SnoRefs::path($ref)) ?? [];
            $sources = [];

            foreach ($affix['unk_e80c332'] ?? [] as $map) {
                if (! is_array($map)) {
                    continue;
                }

                $sources[] = [
                    'source' => $map['tSourceAttribute']['__eAttribute_name__'] ?? null,
                    'destination' => $map['tDestinationAttribute']['__eAttribute_name__'] ?? null,
                ];
            }

            $effects[] = [
                'key' => $key,
                'text' => $this->strings->labelFor('ParagonGlyphAffix', $key, 'desc'),
                'affected_node_rarity' => $affix['eAffectedNodeRarity'] ?? null,
                'required_rarity' => $affix['eRequiredRarity'] ?? null,
                'starting_scalar' => $affix['flStartingBonusScalar'] ?? null,
                'scalar_per_level' => $affix['flAddedBonusScalarPerLevel'] ?? null,
                'passive_power' => SnoRefs::name($affix['snoBonusPassivePower'] ?? null),
                'attribute_map' => $sources,
            ];
        }

        return $effects;
    }

    /**
     * The affix's own description when it has one, otherwise the generic
     * template for its first attribute. Roughly a thousand tempered affixes
     * and every stat affix ship without a description of their own.
     *
     * @param  array<array-key, mixed>  $definition
     * @param  array<string, string>  $strings
     */
    protected function affixText(array $definition, array $strings): ?string
    {
        foreach (['desc', 'codexdesc'] as $label) {
            $text = $strings[$label] ?? null;

            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        $attribute = $this->primaryAttribute($definition);
        $name = $attribute['__eAttribute_name__'] ?? null;

        return is_string($name) && $name !== '' ? $this->strings->attributeDescription($name) : null;
    }

    /**
     * The roll range of one of an affix's attributes — the number its display
     * text substitutes. Values come either from an AttributeFormulas row keyed
     * by item power, or from an inline formula on the attribute itself, and
     * both are run through the evaluator. Whatever refuses to evaluate (a
     * legendary rank, a player-state lookup) keeps its formula text and a null
     * min/max, so the token stays a token in the rendered text.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function affixValueRange(array $definition, int $index = 0): array
    {
        $attribute = $this->attributeAt($definition, $index);

        if ($attribute === []) {
            return [];
        }

        $formulaRef = $attribute['gbidFormula'] ?? null;
        $formulaRow = $this->refs->gameBalanceRow($formulaRef);

        if ($formulaRow !== null) {
            $ranges = [];

            foreach ($formulaRow['arRanges'] ?? [] as $range) {
                if (! is_array($range)) {
                    continue;
                }

                $ranges[] = [
                    'item_power' => (int) ($range['nItemPowerRangeStart'] ?? 0),
                    'formula' => $this->formulaValue($range['tFormula'] ?? null),
                    'clamp' => [
                        'low' => $range['tValueRange']['rangeValue1'] ?? null,
                        'high' => $range['tValueRange']['rangeValue2'] ?? null,
                    ],
                ];
            }

            return [
                'attribute' => $attribute['__eAttribute_name__'] ?? null,
                'source' => 'formula',
                'formula_name' => SnoRefs::name($formulaRef),
                'formula' => $ranges[0]['formula'] ?? null,
                'ranges' => $ranges,
            ] + $this->itemPowerBounds($ranges);
        }

        $inline = $this->formulaValue($attribute['szAttributeFormula'] ?? null);

        if ($inline === null || $inline === '0') {
            return [];
        }

        return [
            'attribute' => $attribute['__eAttribute_name__'] ?? null,
            'source' => 'inline',
            'formula' => $inline,
        ] + $this->bounds($this->evaluator()->evaluate($inline));
    }

    /**
     * Reduce an attribute's piecewise item-power curve to one roll range.
     *
     * The breakpoints are cumulative tiers — a 750-item-power roll uses the
     * 750 range, not the 0 one — so the **highest** breakpoint is the one
     * evaluated, because that is the roll a level-capped character actually
     * sees and the only one worth planning around. Its `nItemPowerRangeStart`
     * doubles as the `ItemPower` the formula reads and is stored alongside the
     * bounds, so a reader knows which tier the numbers describe. A breakpoint
     * that does not evaluate falls back to the next one down rather than
     * giving up on the affix.
     *
     * @param  list<array<string, mixed>>  $ranges
     * @return array{min: float|null, max: float|null, item_power?: int}
     */
    protected function itemPowerBounds(array $ranges): array
    {
        usort($ranges, fn (array $a, array $b) => $b['item_power'] <=> $a['item_power']);

        foreach ($ranges as $range) {
            $formula = $range['formula'] ?? null;

            if (! is_string($formula)) {
                continue;
            }

            $interval = $this->evaluator()->evaluate($formula, ['ItemPower' => $range['item_power']]);

            if ($interval !== null) {
                return $this->bounds($interval) + ['item_power' => $range['item_power']];
            }
        }

        return ['min' => null, 'max' => null];
    }

    /**
     * @param  array{min: float, max: float}|null  $interval
     * @return array{min: float|null, max: float|null}
     */
    protected function bounds(?array $interval): array
    {
        return $interval === null
            ? ['min' => null, 'max' => null]
            : ['min' => $this->round($interval['min']), 'max' => $this->round($interval['max'])];
    }

    /**
     * The values an affix's display text can substitute.
     *
     * `Affix_Value_1` / `Affix_Value_2` are its attributes' roll ranges, and
     * `Affix."Static Value N"` its hand-authored constants. The generic
     * AttributeDescriptions templates spell the same things `{VALUE}` and,
     * when the attribute carries a parameter, `{VALUE1}` for the parameter and
     * `{VALUE2}` for the number — so `{VALUE2}` only means "the first
     * attribute's roll" on a single-attribute affix, and `{VALUE1}` is left
     * unresolved either way because the parameter is a raw gbid hash.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<string, float|array{min: float, max: float}>
     */
    protected function affixTokenValues(array $definition): array
    {
        $attributeCount = count((array) ($definition['ptItemAffixAttributes'] ?? []));
        $tokensByAttribute = [
            0 => $attributeCount === 1 ? ['VALUE', 'VALUE2', 'Affix_Value_1'] : ['VALUE', 'Affix_Value_1'],
            1 => ['VALUE2', 'Affix_Value_2'],
        ];
        $values = [];

        foreach ($tokensByAttribute as $index => $tokens) {
            $range = $this->affixValueRange($definition, $index);

            if (($range['min'] ?? null) === null || ($range['max'] ?? null) === null) {
                continue;
            }

            foreach ($tokens as $token) {
                $values[$token] = ['min' => (float) $range['min'], 'max' => (float) $range['max']];
            }
        }

        foreach (array_values((array) ($definition['arStaticValues'] ?? [])) as $index => $static) {
            if (is_numeric($static)) {
                $values['Affix."Static Value '.$index.'"'] = (float) $static;
                $values['Affix.Static_Value_'.$index] = (float) $static;
            }
        }

        return $values;
    }

    /**
     * The readable rendering of an affix-derived string: markup stripped and
     * every roll token this affix can supply substituted.
     *
     * @param  array<array-key, mixed>  $definition
     */
    protected function affixDisplayText(?string $text, array $definition): ?string
    {
        return $this->tooltips()->render($text, $this->affixTokenValues($definition));
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @param  array<string, string>  $strings
     * @return array<string, mixed>
     */
    protected function affixRaw(array $definition, array $strings): array
    {
        $attributes = [];

        foreach ($definition['ptItemAffixAttributes'] ?? [] as $entry) {
            $attribute = $entry['tAttribute'] ?? null;

            if (! is_array($attribute)) {
                continue;
            }

            $attributes[] = [
                'attribute' => $attribute['__eAttribute_name__'] ?? null,
                'param' => $attribute['nParam'] ?? null,
                'formula' => $this->formulaValue($attribute['szAttributeFormula'] ?? null),
                'formula_name' => SnoRefs::name($attribute['gbidFormula'] ?? null),
                'lower_is_better' => ($entry['bLowerIsBetter'] ?? false) === true,
            ];
        }

        return [
            'sno_id' => $this->snoId($definition),
            'file' => $definition['__fileName__'] ?? null,
            'affix_type' => $definition['eAffixType'] ?? null,
            'family' => SnoRefs::name($definition['gbidAffixFamily'] ?? null),
            'allowed_item_labels' => $definition['arAllowedItemLabels'] ?? [],
            'item_power' => [
                'min' => $definition['nItemPowerMin'] ?? null,
                'max' => $definition['nItemPowerMax'] ?? null,
            ],
            'weight' => $definition['nWeight'] ?? null,
            'max_legendary_ranks' => $definition['arMaxLegendaryRanks'] ?? [],
            'static_values' => $definition['arStaticValues'] ?? [],
            'passive_power' => SnoRefs::name($definition['snoPassivePower'] ?? null),
            'skill_tags' => SnoRefs::names($definition['arAffixSkillTags'] ?? []),
            'attributes' => $attributes,
            'name_prefix' => $strings['name_prefix'] ?? null,
            'name_suffix' => $strings['name_suffix'] ?? null,
            'codex_description' => $strings['codexdesc'] ?? null,
        ];
    }

    /**
     * Tempered affixes carry no `gbidAffixFamily`, and the Affix -> recipe
     * family link is not reconstructible from the Affix files alone, so the
     * `Tempered_<Stat>_<Class>_<Scope>_<Target>_Tier<N>` naming convention
     * stands in as the grouping key until the Recipe SNOs are imported.
     *
     * @param  array<array-key, mixed>  $definition
     */
    protected function temperFamily(string $key, array $definition): ?string
    {
        $family = SnoRefs::name($definition['gbidAffixFamily'] ?? null);

        if ($family !== null) {
            return $family;
        }

        if (($definition['bIsTemperedAffix'] ?? false) !== true) {
            return null;
        }

        $derived = (string) preg_replace('/_Tier\d+$/', '', (string) preg_replace('/^Tempered_/', '', $key));

        return $derived !== '' ? $derived : null;
    }

    /**
     * Aspects have no category field of their own. The affix's
     * `FILTER_Legendary_<Category>` skill tag is the game's own
     * offensive/defensive/utility/mobility/resource grouping.
     *
     * @param  array<array-key, mixed>  $affix
     */
    protected function aspectCategory(array $affix): ?string
    {
        foreach (SnoRefs::names($affix['arAffixSkillTags'] ?? []) as $tag) {
            if (preg_match('/^FILTER_Legendary_(.+)$/', $tag, $matches) === 1) {
                return mb_strtolower($matches[1]);
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    protected function forcedAffixes(array $definition): array
    {
        $affixes = [];

        foreach ($definition['arForcedAffixes'] ?? [] as $ref) {
            $key = SnoRefs::name($ref);

            if ($key === null) {
                continue;
            }

            $affix = $this->optionalJson(SnoRefs::path($ref)) ?? [];
            $strings = $this->strings->labelsFor('Affix', $key);
            $text = $this->affixText($affix, $strings);

            $affixes[] = [
                'key' => $key,
                'sno_id' => SnoRefs::id($ref),
                'name' => $strings['name'] ?? null,
                'text' => $text,
                'display_text' => $this->affixDisplayText($text, $affix),
                'value_range' => $this->affixValueRange($affix),
                'static_values' => $affix['arStaticValues'] ?? [],
            ];
        }

        return $affixes;
    }

    /**
     * Mythic (formerly "uber") uniques carry no flag in this dump: on unique
     * items every `snoSalvageTreasureClass*` is null, `eForcedItemQualityModifier`
     * is 0 and ItemDefinition has no quality mask at all, so nothing structural
     * separates them from ordinary uniques. What does survive the export is
     * naming — mythic content is tagged `*Mythic*` on the item SNO and grouped
     * into `*Mythic*` item families — so that is what this keys on, and
     * everything else is treated as a plain unique. The planned Maxroll
     * cross-check is where this gets tightened.
     *
     * @param  array<array-key, mixed>  $definition
     */
    protected function isMythic(string $key, string $name, array $definition): bool
    {
        $candidates = array_merge([$key, $name], SnoRefs::names($definition['arItemFamilies'] ?? []));

        foreach ($candidates as $candidate) {
            if (mb_stripos($candidate, 'mythic') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * `arBodySlots`, `ePackSlot` and `eWeaponClass` are unnamed enums, so the
     * only slot distinction the dump supports without invented mappings is
     * weapon versus not: `eWeaponClass` is -1 on everything that is not one.
     * The raw ids are kept in `raw` for when a name map turns up.
     *
     * @param  array<array-key, mixed>  $definition
     */
    protected function itemTypeSlot(array $definition): ?string
    {
        $weaponClass = $definition['eWeaponClass'] ?? null;

        return is_numeric($weaponClass) && (int) $weaponClass >= 0 ? 'weapon' : null;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    protected function itemTypeImplicits(array $definition): array
    {
        $implicits = [];

        foreach ($definition['arInnateStatList'] ?? [] as $stat) {
            $attribute = $stat['__eAttribute_name__'] ?? null;

            if (! is_array($stat) || ! is_string($attribute)) {
                continue;
            }

            $implicits[] = [
                'attribute' => $attribute,
                'bonus' => $stat['flBonus'] ?? null,
                'text' => $this->strings->attributeDescription($attribute),
            ];
        }

        return $implicits;
    }

    /**
     * Item labels are the ids an affix's `arAllowedItemLabels` matches against
     * an ItemType's `arItemLabels`. An empty list means "any item type".
     *
     * @param  array<array-key, mixed>  $labels
     * @return list<string>
     */
    protected function itemTypesForLabels(mixed $labels): array
    {
        if (! is_array($labels) || $labels === []) {
            return [];
        }

        $names = [];

        foreach ($labels as $label) {
            if (! is_numeric($label)) {
                continue;
            }

            foreach ($this->itemTypeLabels()[(int) $label] ?? [] as $name) {
                $names[$name] = true;
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * @return array<int, list<string>>
     */
    protected function itemTypeLabels(): array
    {
        if ($this->itemTypeLabels === null) {
            $this->loadItemTypeIndex();
        }

        return $this->itemTypeLabels ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function itemTypeNames(): array
    {
        if ($this->itemTypeNames === null) {
            $this->loadItemTypeIndex();
        }

        return $this->itemTypeNames ?? [];
    }

    /**
     * Read the ItemType directory once for the two lookups affixes, aspects and
     * uniques need, so those datasets do not depend on import order.
     */
    protected function loadItemTypeIndex(): void
    {
        $labels = [];
        $names = [];

        foreach ($this->definitions(self::DIR_ITEM_TYPE, 'itt') as $key => $definition) {
            $name = $this->strings->labelFor('ItemType', $key, 'name') ?? $key;
            $names[$key] = $name;

            foreach ($definition['arItemLabels'] ?? [] as $label) {
                if (is_numeric($label)) {
                    $labels[(int) $label][$name] = true;
                }
            }
        }

        $this->itemTypeLabels = array_map(fn (array $found) => array_keys($found), $labels);
        $this->itemTypeNames = $names;
    }

    /**
     * The single class an 8-wide class mask allows, or null when it allows
     * none or several.
     */
    protected function singleClass(mixed $mask): ?string
    {
        $names = $this->classNames($mask);

        return count($names) === 1 ? $names[0] : null;
    }

    /**
     * @return list<string>
     */
    protected function classNames(mixed $mask): array
    {
        if (! is_array($mask)) {
            return [];
        }

        $names = [];

        foreach (array_values($mask) as $index => $flag) {
            if ((int) $flag === 1 && isset(self::CLASS_NAMES[$index])) {
                $names[] = self::CLASS_NAMES[$index];
            }
        }

        return $names;
    }

    /**
     * The first `ptItemAffixAttributes` entry's attribute specifier.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function primaryAttribute(array $definition): array
    {
        return $this->attributeAt($definition, 0);
    }

    /**
     * One `ptItemAffixAttributes` entry's attribute specifier.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function attributeAt(array $definition, int $index): array
    {
        $attribute = array_values((array) ($definition['ptItemAffixAttributes'] ?? []))[$index]['tAttribute'] ?? null;

        return is_array($attribute) ? $attribute : [];
    }

    /**
     * The evaluator, loaded once with the positional PowerFormulaTables sheet
     * that backs every `Table(n, sLevel)` call.
     */
    protected function textureFrames(): TextureFrames
    {
        return $this->textureFrames ??= new TextureFrames($this->source);
    }

    /**
     * An icon handle's atlas frame, encoded for a nullable jsonb column.
     */
    protected function encodedIcon(mixed $handle): ?string
    {
        $icon = $this->textureFrames()->resolve($handle);

        return $icon === null ? null : json_encode($icon);
    }

    /**
     * The icon handle an item shows in the inventory: its own gendered images
     * first, then its vendor icon, then — one hop — whatever its base item
     * shows. Most base items ship no handle at all (their art hangs off the
     * actor), so null is common and the UI falls back to a letter badge.
     *
     * @param  array<array-key, mixed>  $definition
     */
    protected function itemIconHandle(array $definition, bool $followBaseItem = true): mixed
    {
        foreach ($definition['tInvImages'] ?? [] as $images) {
            if (! is_array($images)) {
                continue;
            }

            foreach (['hDefaultImage', 'hFemaleImage'] as $field) {
                if (is_numeric($images[$field] ?? null) && (int) $images[$field] !== 0) {
                    return $images[$field];
                }
            }
        }

        if (is_numeric($definition['hVendorIcon'] ?? null) && (int) $definition['hVendorIcon'] !== 0) {
            return $definition['hVendorIcon'];
        }

        if ($followBaseItem) {
            $baseItem = $this->optionalJson(SnoRefs::path($definition['snoBaseItem'] ?? null));

            if ($baseItem !== null) {
                return $this->itemIconHandle($baseItem, followBaseItem: false);
            }
        }

        return null;
    }

    /**
     * Persist the slices of the dump the stat calculator reads at request
     * time, so computing a build's stats never touches the source tree.
     */
    protected function importCalcTables(GameVersion $gameVersion): void
    {
        $tables = [
            'attribute_graph' => $this->attributeGraph(),
            'weapon_damage_breakpoints' => $this->weaponDamageBreakpoints(),
            'item_types' => $this->calcItemTypes(),
            'level_scaling' => $this->levelScalingTable(),
            'class_core_stats' => $this->classCoreStats(),
            'globals' => $this->calcGlobals(),
            // sno => atlas object name; the icon manifest and the offline CASC
            // extractor use it to locate the sheets. Populated as a side
            // effect of the icon passes that ran before this.
            'texture_atlases' => $this->textureFrames()->atlases(),
        ];

        $rows = [];

        foreach ($tables as $key => $data) {
            $rows[] = [
                'game_version_id' => $gameVersion->id,
                'key' => $key,
                'data' => json_encode($data),
            ];
        }

        $this->counts['calc_tables'] = $this->replace(CalcTable::class, $gameVersion, $rows, ['key']);
    }

    /**
     * The derived-attribute formula graph from attributes.json: how the game
     * composes totals (Default_HP_Max_Total, Armor_Total, ...) out of leaf
     * attributes. Leaves keep a null formula; their values come from gear,
     * paragon or the engine.
     *
     * @return array<string, array{formula: string|null, default: float|int}>
     */
    protected function attributeGraph(): array
    {
        $graph = [];

        foreach ($this->source->optionalJson(self::FILE_ATTRIBUTES) ?? [] as $name => $entry) {
            if (! is_string($name) || $name === '' || ! is_array($entry)) {
                continue;
            }

            $formula = $entry['formula'] ?? null;

            $graph[$name] = [
                'formula' => is_string($formula) && $formula !== '' ? $formula : null,
                'default' => is_numeric($entry['defaultValue'] ?? null) ? $entry['defaultValue'] + 0 : 0,
            ];
        }

        return $graph;
    }

    /**
     * A weapon's damage roll per item-power breakpoint, one row per
     * attack-speed class, each breakpoint evaluated at its own item power the
     * same way affix ranges are.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function weaponDamageBreakpoints(): array
    {
        $sheet = $this->source->optionalJson(self::FILE_ATTRIBUTE_FORMULAS) ?? [];
        $wanted = array_flip(self::WEAPON_DAMAGE_FORMULAS);
        $breakpoints = [];

        foreach ($sheet['ptData'][0]['tEntries'] ?? [] as $entry) {
            $name = $entry['tHeader']['szName'] ?? null;

            if (! is_string($name) || ! isset($wanted[$name])) {
                continue;
            }

            $ranges = [];

            foreach ($entry['arRanges'] ?? [] as $range) {
                if (! is_array($range)) {
                    continue;
                }

                $itemPower = (int) ($range['nItemPowerRangeStart'] ?? 0);
                $formula = $this->formulaValue($range['tFormula'] ?? null);
                $interval = $formula === null
                    ? null
                    : $this->evaluator()->evaluate($formula, ['ItemPower' => $itemPower]);

                $ranges[] = [
                    'item_power' => $itemPower,
                    'formula' => $formula,
                ] + $this->bounds($interval);
            }

            $breakpoints[$wanted[$name]] = $ranges;
        }

        return $breakpoints;
    }

    /**
     * The per-item-type constants weapon DPS is built from. `unk_b2500f1` is
     * the type's share of the damage budget (verified empirically: 1.0 for
     * two-hand axes down to 0.375 for daggers and foci, 0 for shields) and
     * `unk_4811bbe` the min-to-max spread, uniformly 0.2 — both unnamed in the
     * dump, so they stay flagged as assumptions where the calculator uses them.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function calcItemTypes(): array
    {
        $types = [];

        foreach ($this->definitions(self::DIR_ITEM_TYPE, 'itt') as $key => $definition) {
            $innate = [];

            foreach ($definition['arInnateStatList'] ?? [] as $stat) {
                if (! is_array($stat)) {
                    continue;
                }

                $innate[] = [
                    'attribute' => $stat['__eAttribute_name__'] ?? null,
                    'value' => is_numeric($stat['flBonus'] ?? null) ? $this->round((float) $stat['flBonus']) : null,
                ];
            }

            $types[$key] = [
                'name' => $this->itemTypeNames()[$key] ?? $key,
                'slot' => $this->itemTypeSlot($definition),
                'weapon_class' => $definition['eWeaponClass'] ?? null,
                'damage_multiplier' => is_numeric($definition['unk_b2500f1'] ?? null) ? $this->round((float) $definition['unk_b2500f1']) : null,
                'damage_spread' => is_numeric($definition['unk_4811bbe'] ?? null) ? $this->round((float) $definition['unk_4811bbe']) : null,
                'innate_stats' => $innate,
            ];
        }

        return $types;
    }

    /**
     * Per-character-level scaling: the item power a level sees and the armor /
     * resistance damping curves EHP math needs.
     *
     * @return list<array<string, mixed>>
     */
    protected function levelScalingTable(): array
    {
        $sheet = $this->source->optionalJson(self::FILE_LEVEL_SCALING) ?? [];
        $levels = [];

        foreach ($sheet['ptData'][0]['tEntries'] ?? [] as $entry) {
            if (! is_array($entry) || ! is_numeric($entry['nLevel'] ?? null)) {
                continue;
            }

            $levels[] = [
                'level' => (int) $entry['nLevel'],
                'base_item_power' => $entry['nBaseItemPower'] ?? null,
                'loot_item_power' => $entry['nLootItemPower'] ?? null,
                'real_item_power' => $entry['nRealItemPower'] ?? null,
                'armor_damping' => $entry['nArmorDampingFactor'] ?? null,
                'armor_dr_scalar' => isset($entry['flArmorDamageReductionScalar']) ? $this->round((float) $entry['flArmorDamageReductionScalar']) : null,
                'resistance_damping' => $entry['nResistanceDampingFactor'] ?? null,
                'resistance_dr_scalar' => isset($entry['flResistanceDamageReductionScalar']) ? $this->round((float) $entry['flResistanceDamageReductionScalar']) : null,
                'estimated_armor' => $entry['nPlayerEstimatedArmor'] ?? null,
                'ideal_armor' => $entry['nPlayerIdealArmor'] ?? null,
                'estimated_resistance' => $entry['nPlayerEstimatedResistance'] ?? null,
                'ideal_resistance' => $entry['nPlayerIdealResistance'] ?? null,
                'class_base_damage_scalar' => $entry['flClassBaseDamageScalar'] ?? null,
            ];
        }

        return $levels;
    }

    /**
     * Which core stat feeds each class's damage buckets and by how much —
     * PlayerClass `arCoreStatBenefit`, slots keyed by the benefit they feed.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function classCoreStats(): array
    {
        $classes = [];

        foreach ($this->definitions(self::DIR_PLAYER_CLASS, 'pcl') as $key => $definition) {
            $benefits = [];

            foreach ($definition['arCoreStatBenefit'] ?? [] as $slot => $benefit) {
                foreach ((array) ($benefit['unk_8754bdb'] ?? []) as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $coreStat = $entry['eContributingCoreStat'] ?? null;

                    $benefits[] = [
                        'slot' => $slot,
                        'core_stat' => is_numeric($coreStat) ? (self::CORE_STATS[(int) $coreStat] ?? null) : null,
                        'scalar' => is_numeric($entry['flContributionScalar'] ?? null) ? $this->round((float) $entry['flContributionScalar']) : null,
                    ];
                }
            }

            $classes[$key] = $benefits;
        }

        return $classes;
    }

    /**
     * The engine constants the calculator and paragon planner read, allowlisted
     * out of globals.glo.json.
     *
     * @return array<string, mixed>
     */
    protected function calcGlobals(): array
    {
        $content = $this->source->optionalJson(self::FILE_GLOBALS)['ptContent'][0] ?? [];
        $globals = [];

        foreach (self::GLOBAL_FIELDS as $field) {
            if (array_key_exists($field, $content)) {
                $globals[$field] = $content[$field];
            }
        }

        return $globals;
    }

    protected function evaluator(): FormulaEvaluator
    {
        return $this->evaluator ??= new FormulaEvaluator($this->powerFormulaTables());
    }

    protected function tooltips(): TooltipText
    {
        return $this->tooltips ??= new TooltipText($this->evaluator());
    }

    /**
     * PowerFormulaTables rows carry no id of their own: `Table(34, sLevel)`
     * means the 35th entry of the sheet, so the index has to stay positional.
     *
     * @return array<int, list<float>>
     */
    protected function powerFormulaTables(): array
    {
        $sheet = $this->optionalJson(self::FILE_POWER_FORMULA_TABLES) ?? [];
        $tables = [];

        foreach (array_values((array) ($sheet['ptData'][0]['tEntries'] ?? [])) as $index => $entry) {
            $values = is_array($entry) ? ($entry['flValue'] ?? null) : null;

            if (is_array($values)) {
                $tables[$index] = array_map(fn (mixed $value): float => (float) $value, array_values($values));
            }
        }

        return $tables;
    }

    /**
     * @param  array{min: float, max: float}  $interval
     * @return float|array{min: float, max: float}
     */
    protected function compactInterval(array $interval): float|array
    {
        return $interval['min'] === $interval['max']
            ? $this->round($interval['min'])
            : ['min' => $this->round($interval['min']), 'max' => $this->round($interval['max'])];
    }

    protected function round(float $value): float
    {
        return round($value, self::VALUE_PRECISION);
    }

    /**
     * Formulas are `{ value: "<expr>", compiled: "<base64 bytecode>" }`; only
     * the readable half is ever stored.
     */
    protected function formulaValue(mixed $formula): ?string
    {
        if (! is_array($formula)) {
            return null;
        }

        $value = $formula['value'] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $specifiers
     * @return list<string>
     */
    protected function attributeNames(mixed $specifiers): array
    {
        if (! is_array($specifiers)) {
            return [];
        }

        $names = [];

        foreach ($specifiers as $specifier) {
            $name = $specifier['__eAttribute_name__'] ?? null;

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
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
