<?php

namespace App\Domain\D4\Import;

/**
 * Decides whether a datamined entity is live game content.
 *
 * The dump is a straight export of the game's asset tree, so it carries the
 * designers' scratch files, marketing builds and season-gated content next to
 * the real thing. Nothing here drops a row: the importer stores everything and
 * writes the verdict to `is_released`, because "unreleased today" becomes
 * "released next patch" and a hidden row is cheaper to flip than a missing one.
 *
 * Two independent signals feed the verdict: junk naming conventions, and the
 * structural gates the game itself honours. A later phase will cross-check the
 * result against Maxroll's compiled data.
 */
class ContentFilter
{
    /**
     * Placeholder objects that exist once per SNO group.
     *
     * @var list<string>
     */
    public const JUNK_NAMES = [
        'Axe Bad Data',
    ];

    /**
     * @var list<string>
     */
    public const JUNK_PREFIXES = [
        'TEST_',
        'Test_',
        'test_',
        'TESTstephen_',
        'TEMPLATE_',
        'AAA',
        'zz',
        'DONOTSHIP',
    ];

    /**
     * @var list<string>
     */
    public const JUNK_SUFFIXES = [
        '_Q4BLOG',
        '_BlackSabbath',
        'TestExcelSheet',
    ];

    /**
     * @param  array<string, mixed>  $definition
     * @param  bool  $honourVisibleInUi  Powers alone carry a meaningful `bVisibleInUI`.
     */
    public function isReleased(string $name, array $definition = [], bool $honourVisibleInUi = false): bool
    {
        return $this->reasons($name, $definition, $honourVisibleInUi) === [];
    }

    /**
     * Why an entity is not considered live content. An empty list means it is.
     *
     * @param  array<string, mixed>  $definition
     * @param  bool  $honourVisibleInUi  Powers alone carry a meaningful `bVisibleInUI`.
     * @return list<string>
     */
    public function reasons(string $name, array $definition = [], bool $honourVisibleInUi = false): array
    {
        $reasons = [];

        if ($this->isJunkName($name)) {
            $reasons[] = 'junk_name';
        }

        if (($definition['bIgnoreOnLoad'] ?? null) === true) {
            $reasons[] = 'ignored_on_load';
        }

        if ($honourVisibleInUi && ($definition['bVisibleInUI'] ?? null) === false) {
            $reasons[] = 'hidden_in_ui';
        }

        $seasons = $definition['tRequirementsToBeActive']['arSeasons'] ?? [];

        if (is_array($seasons) && $seasons !== []) {
            $reasons[] = 'season_gated';
        }

        if (($definition['bSeasonItem'] ?? null) === true) {
            $reasons[] = 'season_item';
        }

        $license = $definition['dwContentLicenseRequirements'] ?? 0;

        if (is_numeric($license) && (int) $license !== 0) {
            $reasons[] = 'license_gated';
        }

        return $reasons;
    }

    /**
     * Whether an SNO name follows one of the dump's scratch-file conventions.
     */
    public function isJunkName(string $name): bool
    {
        if (in_array($name, self::JUNK_NAMES, true)) {
            return true;
        }

        foreach (self::JUNK_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        foreach (self::JUNK_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
