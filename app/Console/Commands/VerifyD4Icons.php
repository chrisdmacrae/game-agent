<?php

namespace App\Console\Commands;

use App\Domain\D4\Import\ContentFilter;
use App\Domain\D4\Import\D4DataSource;
use App\Domain\D4\Import\SnoRefs;
use App\Domain\D4\Import\TextureFrames;
use Illuminate\Console\Command;

/**
 * Reports every icon handle the importer would persist that no cloned 2D atlas
 * resolves, so a sparse-checkout regression (or a patch moving art) is caught
 * before it ships as a page full of letter badges.
 *
 * Unreleased content and icon-less base items are expected noise and only
 * reported; a missing handle on released content fails the run.
 */
class VerifyD4Icons extends Command
{
    protected $signature = 'd4:verify-icons
        {--from-git : Verify against the sparse git clone instead of the published artifact}';

    protected $description = 'Verify every Diablo IV entity icon handle resolves to a cloned texture atlas frame';

    /**
     * Entities whose icon handles are known to resolve nowhere in the Texture
     * group's 2D atlases (verified against build 3.1.3.73224): leftover talent
     * powers superseded by the modifier rework, still class-gated and visible
     * in the dump. They render as letter badges. A new patch adding entries
     * here deserves a fresh look before it is accepted.
     *
     * @var list<string>
     */
    protected const KNOWN_MISSING = [
        'skill Druid_Talent_Hybrid_T5_N5',
        'skill Necromancer_Talent_Hybrid_T5_N2',
        'skill Rogue_Talent_Cunning_T5_N4',
        'skill Sorcerer_Talent_Hybrid_T3_N1',
        'skill Sorcerer_Talent_Hybrid_T3_N2',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '2G');

        $source = new D4DataSource(fromGit: (bool) $this->option('from-git'));

        if (! is_dir($source->treePath())) {
            $this->error('No d4data source tree on disk. Run d4:import first.');

            return self::FAILURE;
        }

        $frames = new TextureFrames($source);
        $filter = new ContentFilter;

        $missing = ['failing' => [], 'expected' => []];
        $checked = 0;

        $record = function (string $entity, string $key, mixed $handle, bool $isReleased) use ($frames, &$missing, &$checked): void {
            if (! is_numeric($handle) || (int) $handle === 0) {
                return;
            }

            $checked++;

            if ($frames->resolve($handle) === null) {
                $bucket = in_array("{$entity} {$key}", self::KNOWN_MISSING, true) || ! $isReleased
                    ? 'expected'
                    : 'failing';

                $missing[$bucket][] = "{$entity} {$key} (handle ".(int) $handle.')';
            }
        };

        foreach ($this->definitions($source, 'json/base/meta/Power', '.pow.json') as $key => $definition) {
            if (SnoRefs::name($definition['snoClassRequirement'] ?? null) === null) {
                continue; // Not a player skill; the importer skips it too.
            }

            $record('skill', $key, $definition['hIconNormal'] ?? null, $filter->isReleased($key, $definition, honourVisibleInUi: true));
        }

        foreach ($this->definitions($source, 'json/base/meta/Aspect', '.asp.json') as $key => $definition) {
            $record('aspect', $key, $definition['hIconOverride'] ?? null, $filter->isReleased($key, $definition));
        }

        foreach ($this->definitions($source, 'json/base/meta/Item', '.itm.json') as $key => $definition) {
            if ((int) ($definition['eMagicType'] ?? 0) !== 2) {
                continue;
            }

            foreach ($definition['tInvImages'] ?? [] as $images) {
                $record('unique', $key, is_array($images) ? ($images['hDefaultImage'] ?? null) : null, $filter->isReleased($key, $definition));
            }

            $record('unique', $key, $definition['hVendorIcon'] ?? null, $filter->isReleased($key, $definition));
        }

        foreach ($this->definitions($source, 'json/base/meta/ParagonNode', '.pgn.json') as $key => $definition) {
            $record('paragon-node', $key, $definition['hIconMask'] ?? null, $filter->isReleased($key, $definition));
        }

        foreach ($this->definitions($source, 'json/base/meta/ParagonBoard', '.pbd.json') as $key => $definition) {
            $record('paragon-board', $key, $definition['legendaryNodeIcon'] ?? null, $filter->isReleased($key, $definition));
        }

        $this->info("Checked {$checked} icon handles.");

        foreach ($missing['expected'] as $line) {
            $this->line("  [expected] {$line}");
        }

        foreach ($missing['failing'] as $line) {
            $this->warn("  [missing] {$line}");
        }

        if ($missing['failing'] !== []) {
            $this->error(count($missing['failing']).' released entities have icon handles no cloned atlas resolves. Widen D4DataSource::SPARSE_FILE_PATTERNS, or record them in KNOWN_MISSING to accept the letter-badge fallback.');

            return self::FAILURE;
        }

        $this->info('Every released entity icon resolves.');

        return self::SUCCESS;
    }

    /**
     * @return iterable<string, array<array-key, mixed>>
     */
    protected function definitions(D4DataSource $source, string $directory, string $suffix): iterable
    {
        foreach ($source->files($directory) as $file) {
            if (! str_ends_with($file, $suffix)) {
                continue;
            }

            $definition = $source->optionalJson($directory.'/'.$file);

            if (is_array($definition)) {
                yield substr($file, 0, -strlen($suffix)) => $definition;
            }
        }
    }
}
