<?php

namespace App\Domain\D4\Import;

/**
 * Reads the two cross-reference shapes the d4data dump uses.
 *
 * `DT_SNO` references are pre-resolved inline by the dumper — they carry the
 * target's name and file path — so they need no lookup table at all and the
 * readers for them are static.
 *
 * `DT_GBID` references are not resolved: their `group` is an eGameBalanceType
 * id (NOT an SNO group), which maps to one or more `.gam` sheets, and the row
 * is the one whose `tHeader.szNameGBIDHash` equals the reference's `__raw__`.
 * Resolving those needs the source tree, so that half is instance state.
 */
class SnoRefs
{
    /**
     * eGameBalanceType id => sheet paths relative to the tree root.
     *
     * @var array<int, list<string>>|null
     */
    protected ?array $sheets = null;

    /**
     * eGameBalanceType id => gbid hash => row.
     *
     * @var array<int, array<int, array<string, mixed>>>
     */
    protected array $rowsByType = [];

    public function __construct(
        protected D4DataSource $source,
    ) {}

    /**
     * The pre-resolved name of a `DT_SNO` or `DT_GBID` reference.
     */
    public static function name(mixed $ref): ?string
    {
        if (! is_array($ref)) {
            return null;
        }

        $name = $ref['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The SNO id (`DT_SNO`) or gbid hash (`DT_GBID`) a reference points at.
     */
    public static function id(mixed $ref): ?int
    {
        if (! is_array($ref)) {
            return null;
        }

        $raw = $ref['__raw__'] ?? null;

        return is_numeric($raw) ? (int) $raw : null;
    }

    /**
     * The referenced file's path relative to the source tree root.
     */
    public static function path(mixed $ref): ?string
    {
        if (! is_array($ref)) {
            return null;
        }

        $target = $ref['__targetFileName__'] ?? null;

        return is_string($target) && $target !== '' ? 'json/'.$target.'.json' : null;
    }

    /**
     * The names of every resolvable reference in a list.
     *
     * @return list<string>
     */
    public static function names(mixed $refs): array
    {
        if (! is_array($refs)) {
            return [];
        }

        $names = [];

        foreach ($refs as $ref) {
            $name = self::name($ref);

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Resolve a `DT_GBID` reference to the GameBalance row it names.
     *
     * @return array<string, mixed>|null
     */
    public function gameBalanceRow(mixed $ref): ?array
    {
        if (! is_array($ref)) {
            return null;
        }

        $group = $ref['group'] ?? null;
        $hash = $ref['__raw__'] ?? null;

        if (! is_numeric($group) || ! is_numeric($hash)) {
            return null;
        }

        return $this->rowsForType((int) $group)[(int) $hash] ?? null;
    }

    /**
     * Every decoded row of an eGameBalanceType, keyed by its gbid hash. Sheets
     * whose payload the dumper could not decode (`ptData: [null]`) contribute
     * nothing rather than blowing up.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForType(int $type): array
    {
        if (isset($this->rowsByType[$type])) {
            return $this->rowsByType[$type];
        }

        $rows = [];

        foreach ($this->sheetsForType($type) as $path) {
            $sheet = $this->source->optionalJson($path);

            if ($sheet === null) {
                continue;
            }

            foreach ($sheet['ptData'][0]['tEntries'] ?? [] as $entry) {
                $hash = $entry['tHeader']['szNameGBIDHash'] ?? null;

                if (is_array($entry) && is_numeric($hash)) {
                    $rows[(int) $hash] = $entry;
                }
            }
        }

        return $this->rowsByType[$type] = $rows;
    }

    /**
     * The `.gam` sheets that back an eGameBalanceType. One type can map to
     * several files (43 => SkillTreeRewards and Warplans_SkillTreeRewards).
     *
     * @return list<string>
     */
    public function sheetsForType(int $type): array
    {
        $this->sheets ??= $this->loadSheetIndex();

        return $this->sheets[$type] ?? [];
    }

    /**
     * json/eGameBalanceType.json is the published index; when it is absent from
     * a trimmed tree the GameBalance directory is scanned instead, since every
     * sheet declares its own `eGameBalanceType`.
     *
     * @return array<int, list<string>>
     */
    protected function loadSheetIndex(): array
    {
        $index = [];
        $published = $this->source->optionalJson('json/eGameBalanceType.json');

        if ($published !== null) {
            foreach ($published as $type => $paths) {
                if (! is_numeric($type) || ! is_array($paths)) {
                    continue;
                }

                foreach ($paths as $path) {
                    if (is_string($path) && $path !== '') {
                        $index[(int) $type][] = $path;
                    }
                }
            }

            return $index;
        }

        $directory = 'json/base/meta/GameBalance';

        foreach ($this->source->files($directory) as $file) {
            $sheet = $this->source->optionalJson($directory.'/'.$file);
            $type = $sheet['eGameBalanceType'] ?? null;

            if (is_numeric($type)) {
                $index[(int) $type][] = $directory.'/'.$file;
            }
        }

        return $index;
    }
}
