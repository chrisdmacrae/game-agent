<?php

namespace Tests\Fixtures;

use App\Domain\D4\Import\D4DataSource;
use App\Domain\D4\Import\D4Importer;
use App\Models\GameVersion;

/**
 * Seeds D4 feature tests by running the real importer over the fixture tree in
 * tests/Fixtures/d4data — a referentially intact slice of the d4data repo, so
 * nothing here touches the network or the live database.
 */
class D4Seeder
{
    public static function importer(): D4Importer
    {
        return new D4Importer(new D4DataSource(
            fromGit: false,
            treePath: base_path('tests/Fixtures/d4data'),
            fingerprintOverride: 'fixturecommitsha',
        ));
    }

    public static function seed(): GameVersion
    {
        return self::importer()->import();
    }
}
