<?php

namespace App\Domain\D4\Calc;

use App\Domain\D4\D4Context;
use App\Models\D4\Affix;
use App\Models\D4\ParagonBoard;
use App\Models\D4\Skill;

/**
 * Computes a Diablo IV build's baseline stats from its payload and the
 * imported calculator tables: base weapon damage, per-skill DPS through the
 * additive damage buckets, life, armor and effective HP.
 *
 * This is deliberately a BASELINE engine, not a simulator: contributions come
 * from what the payload states in structured form (affixes with keys and
 * values, paragon nodes, skill ranks), engine-side curves the dump never
 * ships come from Calibration, and every approximation the result rests on is
 * named in `assumptions` so the numbers stay honest.
 */
class D4BuildComputer
{
    /** Class name => its main damage stat and per-point scalar, from the class data. */
    protected ?array $mainStats = null;

    /** @var array<string, Affix|null> */
    protected array $affixCache = [];

    /** @var list<string> */
    protected array $assumptions = [];

    public function __construct(protected D4Context $context) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null null when there is no imported data to compute against
     */
    public function compute(array $payload, ?int $versionId = null): ?array
    {
        try {
            $versionId ??= $this->context->versionId();
        } catch (\RuntimeException) {
            return null;
        }

        $this->assumptions = [];
        $this->affixCache = [];

        $tables = new CalcTables($versionId);

        if ($tables->table('level_scaling') === []) {
            return null; // Data imported before the calculator tables existed.
        }

        $level = is_numeric($payload['level'] ?? null) ? (int) $payload['level'] : 60;

        if (! is_numeric($payload['level'] ?? null)) {
            $this->assumptions[] = 'No level on the build; computed at level 60.';
        }

        $itemPower = (int) ($tables->levelScaling($level)['loot_item_power'] ?? 750);

        $bag = new StatBag;
        $coverage = $this->collectContributions($bag, $payload, $versionId, $tables);

        $weapon = $this->bestWeapon($payload, $tables, new WeaponModel($tables), $itemPower);
        $offence = $this->computeOffence($payload, $bag, $weapon, $tables, $versionId, $level);
        $defence = $this->computeDefence($payload, $bag, $tables, $level);

        if ($bag->unmapped !== []) {
            $this->assumptions[] = count($bag->unmapped).' affix attribute(s) had no damage/defence bucket and were ignored: '
                .implode(', ', array_slice(array_keys($bag->unmapped), 0, 4)).'.';
        }

        return [
            'dps' => $offence['dps'],
            'ehp' => $defence['ehp'],
            'life' => $defence['life'],
            'armor' => $defence['armor'],
            'item_power' => $itemPower,
            'weapon' => $weapon,
            'skills' => $offence['skills'],
            'offence_rows' => $offence['rows'],
            'defence_rows' => $defence['rows'],
            'coverage' => $coverage,
            'assumptions' => $this->assumptions,
        ];
    }

    /**
     * Everything the payload states in structured form lands in the bag:
     * gear affix rolls, tempered rolls, and the paragon core-stat estimate.
     *
     * @param  array<string, mixed>  $payload
     * @return array{structured_affixes: int, unstructured_slots: int, paragon_nodes: int}
     */
    protected function collectContributions(StatBag $bag, array $payload, int $versionId, CalcTables $tables): array
    {
        $structured = 0;
        $unstructuredSlots = 0;

        foreach ($this->gearItems($payload) as $item) {
            $sawStructured = false;
            $sawAny = false;

            foreach ($item['affixes'] ?? [] as $entry) {
                $sawAny = true;
                $key = is_array($entry) ? ($entry['affix'] ?? null) : null;

                if (! is_string($key) || $key === '') {
                    continue;
                }

                if ($this->applyAffix($bag, $key, $entry['value'] ?? null, $versionId)) {
                    $structured++;
                    $sawStructured = true;
                }
            }

            foreach ($item['tempered'] ?? [] as $temper) {
                if (is_array($temper) && is_string($temper['affix'] ?? null)
                    && $this->applyAffix($bag, $temper['affix'], $temper['value'] ?? null, $versionId)) {
                    $structured++;
                    $sawStructured = true;
                }
            }

            if ($sawAny && ! $sawStructured) {
                $unstructuredSlots++;
            }
        }

        if ($unstructuredSlots > 0) {
            $this->assumptions[] = "{$unstructuredSlots} gear item(s) carry only unstructured affix text, which contributes nothing here.";
        }

        $paragonNodes = $this->applyParagon($bag, $payload, $versionId);

        return [
            'structured_affixes' => $structured,
            'unstructured_slots' => $unstructuredSlots,
            'paragon_nodes' => $paragonNodes,
        ];
    }

    /**
     * One structured affix roll into the bag. The roll is normalised onto the
     * game's internal scale: percentage stats are stored as fractions, and a
     * value entered as the displayed percentage is divided back down.
     */
    protected function applyAffix(StatBag $bag, string $key, mixed $value, int $versionId): bool
    {
        $affix = $this->affixCache[$key] ??= Affix::forVersion($versionId)
            ->where(fn ($query) => $query->whereLike('key', $key)->orWhereLike('name', $key))
            ->orderByDesc('is_released')
            ->first();

        if ($affix === null) {
            return false;
        }

        $range = is_array($affix->value_range) ? $affix->value_range : [];
        $attribute = $range['attribute'] ?? null;

        if (! is_string($attribute) || $attribute === '') {
            return false;
        }

        $roll = $this->normalizeRoll($range, $value);

        if ($roll === null) {
            return false;
        }

        $bag->add($attribute, $roll);

        return true;
    }

    /**
     * @param  array<string, mixed>  $range
     */
    protected function normalizeRoll(array $range, mixed $value): ?float
    {
        $min = is_numeric($range['min'] ?? null) ? (float) $range['min'] : null;
        $max = is_numeric($range['max'] ?? null) ? (float) $range['max'] : null;

        if (! is_numeric($value)) {
            // No roll listed: plan around the midpoint of the datamined range.
            return $min !== null && $max !== null ? ($min + $max) / 2 : null;
        }

        $value = (float) $value;

        if ($max === null || $min === null) {
            return $value;
        }

        if ($value >= $min && $value <= $max) {
            return $value;
        }

        // The displayed-percentage scale (x100 of the stored fraction).
        if ($value / 100 >= $min && $value / 100 <= $max) {
            return $value / 100;
        }

        // Out of range either way — the validator warns; count it as entered,
        // scaled down when it is clearly a percentage figure.
        return $max <= 1.0 && $value > 1.0 ? $value / 100 : $value;
    }

    /**
     * Paragon contribution: allocated core-stat nodes counted at the
     * calibrated per-node grant. Node cells carry attribute names but not
     * magnitudes, so this is an estimate and says so.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function applyParagon(StatBag $bag, array $payload, int $versionId): int
    {
        $entries = collect(is_array($payload['paragon'] ?? null) ? $payload['paragon'] : [])
            ->filter(fn (mixed $entry) => is_array($entry) && ($entry['nodes'] ?? []) !== []);

        if ($entries->isEmpty()) {
            return 0;
        }

        $boards = ParagonBoard::forVersion($versionId)
            ->whereIn('name', $entries->pluck('board')->filter()->unique()->values()->all())
            ->get(['name', 'grid'])
            ->keyBy(fn (ParagonBoard $board) => mb_strtolower($board->name));

        $statNodes = 0;
        $total = 0;

        foreach ($entries as $entry) {
            $board = $boards[mb_strtolower((string) $entry['board'])] ?? null;
            $grid = $board !== null && is_array($board->grid) ? $board->grid : [];

            foreach ($entry['nodes'] as $node) {
                $cell = $grid[$node['row'] ?? -1][$node['col'] ?? -1] ?? null;

                if (! is_array($cell)) {
                    continue;
                }

                $total++;

                foreach ((array) ($cell['attributes'] ?? []) as $attribute) {
                    if (is_string($attribute) && str_contains(strtolower($attribute), '_core')) {
                        $statNodes++;
                        break;
                    }
                }
            }
        }

        if ($statNodes > 0) {
            $bag->mainStat += $statNodes * Calibration::PARAGON_NODE_MAIN_STAT;
            $this->assumptions[] = "{$statNodes} allocated paragon stat nodes estimated at "
                .Calibration::PARAGON_NODE_MAIN_STAT.' main stat each; node magnitudes are not in the data.';
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function bestWeapon(array $payload, CalcTables $tables, WeaponModel $model, int $itemPower): ?array
    {
        $best = null;

        foreach ($payload['gear']['weapons'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $damage = $model->damage($item, $itemPower);

            if ($damage !== null && ($best === null || $damage['dps'] > $best['dps'])) {
                $best = $damage + ['name' => $item['name'] ?? null];
            }
        }

        if ($best !== null) {
            $this->assumptions[] = 'Weapon damage uses the datamined roll at item power '.$best['item_power']
                .' with the empirical 0.2 min-max spread and type multiplier.';
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{dps: int|null, skills: list<array<string, mixed>>, rows: list<array{label: string, value: string}>}
     */
    protected function computeOffence(array $payload, StatBag $bag, ?array $weapon, CalcTables $tables, int $versionId, int $level): array
    {
        $mainStat = $this->mainStatFor($payload['class'] ?? null, $versionId);

        if ($mainStat !== null && $bag->mainStat > 0) {
            $bag->additiveDamage += $bag->mainStat * Calibration::MAIN_STAT_DAMAGE_PER_POINT * $mainStat['scalar'];
        }

        $critScalar = $tables->global('flPlayerCritDamageScalar');
        $baseCritDamage = is_numeric($critScalar) ? (float) $critScalar : 0.5;

        $assumeVulnerable = $bag->vulnerable > 0;

        if ($assumeVulnerable) {
            $this->assumptions[] = 'Vulnerable bonuses applied at 100% uptime.';
        }

        $multiplier = $bag->damageMultiplier(Calibration::BASE_CRIT_CHANCE, $baseCritDamage, $assumeVulnerable);

        $skillModel = new SkillDamageModel($tables);
        $skills = [];
        $headline = null;

        foreach ($payload['equipped_skills'] ?? [] as $setup) {
            $name = is_array($setup) ? ($setup['skill'] ?? null) : null;

            if (! is_string($name) || $name === '' || $weapon === null) {
                continue;
            }

            $skill = Skill::forVersion($versionId)
                ->whereLike('name', $name)
                ->orderByDesc('is_released')
                ->first();

            if ($skill === null) {
                continue;
            }

            $rank = is_numeric($setup['rank'] ?? null) ? (int) $setup['rank'] : 1;
            $hit = $skillModel->baseHit($skill, $rank, $level);

            if ($hit === null) {
                continue;
            }

            // An approximate coefficient is already per-second (a channeled
            // skill's numerator); multiplying by attack speed again would
            // double-count the tick rate.
            $attacksPerSecond = $hit['approximate']
                ? 1 + $bag->attackSpeed
                : $weapon['attacks_per_second'] * (1 + $bag->attackSpeed);

            if ($hit['approximate']) {
                $this->assumptions[] = "\"{$skill->name}\"'s tick-rate divisor is not in the data; its coefficient is treated as damage per second.";
            }

            $dps = $weapon['average_hit'] * $hit['coefficient'] * $multiplier * $attacksPerSecond;

            $skills[] = [
                'skill' => $skill->name,
                'rank' => $hit['rank'],
                'weapon_damage_percent' => round($hit['coefficient'] * 100, 1),
                'dps' => (int) round($dps),
            ];

            $headline = max($headline ?? 0, (int) round($dps));
        }

        if ($skills === [] && $weapon !== null) {
            $this->assumptions[] = 'No equipped skill has an evaluable damage payload; the headline DPS is the bare weapon DPS.';
            $headline = (int) round($weapon['dps'] * $multiplier);
        }

        $rows = [];

        if ($weapon !== null) {
            $rows[] = ['label' => 'Weapon DPS', 'value' => $this->formatNumber($weapon['dps'])];
        } else {
            $this->assumptions[] = 'No weapon with a resolvable item type; skill DPS cannot be computed.';
        }

        if ($headline !== null) {
            $rows[] = ['label' => 'Computed DPS', 'value' => $this->formatNumber($headline)];
        }

        $rows[] = ['label' => 'Damage multiplier', 'value' => 'x'.round($multiplier, 2)];

        return ['dps' => $headline, 'skills' => $skills, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ehp: int|null, life: int, armor: int, rows: list<array{label: string, value: string}>}
     */
    protected function computeDefence(array $payload, StatBag $bag, CalcTables $tables, int $level): array
    {
        $life = (Calibration::baseLife($level)
                + $bag->flatLife * Calibration::FLAT_LIFE_LEVEL_SCALAR)
            * (1 + $bag->lifePercent);

        $this->assumptions[] = 'Base life uses the calibration curve ('.Calibration::BASE_LIFE.' + '
            .Calibration::LIFE_PER_LEVEL.'/level), pending in-game verification.';

        $strengthArmor = $bag->mainStat * Calibration::ARMOR_PER_STRENGTH;
        $computedArmor = ($bag->armor + $strengthArmor) * (1 + $bag->armorPercent);

        // A hand-reported sheet armor outranks the estimate.
        $armor = is_numeric($payload['armor'] ?? null) && (int) $payload['armor'] > 0
            ? (float) $payload['armor']
            : $computedArmor;

        $mitigation = new MitigationModel($tables);
        $ehp = $life > 0 ? $mitigation->effectiveHp($life, $armor, $level) : null;

        $rows = [
            ['label' => 'Life', 'value' => $this->formatNumber($life)],
            ['label' => 'Armor', 'value' => $this->formatNumber($armor)],
            ['label' => 'Armor DR', 'value' => round($mitigation->armorReduction($armor, $level) * 100).'%'],
        ];

        if ($ehp !== null) {
            $rows[] = ['label' => 'Effective HP', 'value' => $this->formatNumber($ehp)];
        }

        return [
            'ehp' => $ehp === null ? null : (int) round($ehp),
            'life' => (int) round($life),
            'armor' => (int) round($armor),
            'rows' => $rows,
        ];
    }

    /**
     * The class's main damage stat: the largest core-stat contribution slot
     * in the imported class data (Barbarian: Strength x1.10, Sorcerer:
     * Intelligence x1.25, ...).
     *
     * @return array{stat: string, scalar: float}|null
     */
    protected function mainStatFor(mixed $className, int $versionId): ?array
    {
        if (! is_string($className) || $className === '') {
            return null;
        }

        $this->mainStats ??= (new CalcTables($versionId))->table('class_core_stats');

        $benefits = null;

        foreach ($this->mainStats as $name => $entries) {
            if (mb_strtolower((string) $name) === mb_strtolower($className)) {
                $benefits = $entries;
                break;
            }
        }

        $best = null;

        foreach (is_array($benefits) ? $benefits : [] as $benefit) {
            if (! is_array($benefit) || ! is_numeric($benefit['scalar'] ?? null) || ! is_string($benefit['core_stat'] ?? null)) {
                continue;
            }

            if ($best === null || (float) $benefit['scalar'] > $best['scalar']) {
                $best = ['stat' => $benefit['core_stat'], 'scalar' => (float) $benefit['scalar']];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function gearItems(array $payload): array
    {
        $gear = is_array($payload['gear'] ?? null) ? $payload['gear'] : [];
        $items = [];

        foreach ($gear as $slot => $item) {
            if ($slot === 'weapons') {
                foreach (is_array($item) ? $item : [] as $weapon) {
                    if (is_array($weapon)) {
                        $items[] = $weapon;
                    }
                }
            } elseif (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function formatNumber(float $value): string
    {
        return match (true) {
            $value >= 1_000_000 => round($value / 1_000_000, 1).'M',
            $value >= 10_000 => round($value / 1_000).'k',
            default => (string) round($value),
        };
    }
}
