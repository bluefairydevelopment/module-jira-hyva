<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;

/**
 * This package is sold. Until 1.1.1 it shipped `LICENSE.md` containing the
 * **Open Software License v3.0** — an OSI-approved open source licence that
 * grants a worldwide, royalty-free, sublicensable right to reproduce, modify and
 * redistribute — while `composer.json` declared `"license": ["proprietary"]`.
 * The two statements contradicted each other and the open one was the one in the
 * merchant's `vendor/` directory.
 *
 * Almost certainly inherited by copying Magento's own licence file, which is
 * OSL-3.0. Nothing here is a legal opinion; this test asserts only that the two
 * declarations agree and that the shipped text is the house instrument.
 *
 * **Estate measurement, 2026-09-11, 35 BlueFairy packages:**
 *
 *     8   ship a correct proprietary notice          (Printify x5, Slack Chat x3)
 *    10   declare proprietary, ship OSL-3.0          (this package was one)
 *     3   declare proprietary, ship the GNU GPL      (cart-products x3)
 *     3   declare themselves open source in composer.json
 *    12   ship no licence file at all                (module-jira-graph-ql was one)
 *
 * **Not one test in the estate opened a licence file.** That is the whole reason
 * 27 of 35 could drift: phpcs and phpstan do not read `.md` or `.txt`, and a
 * missing licence file breaks no build. So this check exists, and every assertion
 * below is exercised against a specimen it plants itself — an assertion of
 * absence over prose is the shape most likely to be vacuous.
 */
class TheLicenceAMerchantReceivesTest extends TestCase
{
    /**
     * Grant language from the licences actually found in this estate. Matching
     * any of these means the shipped text gives the merchant rights the sale does
     * not.
     */
    private const OPEN_SOURCE_GRANTS = [
        'Open Software License',
        'GNU GENERAL PUBLIC LICENSE',
        'Academic Free License',
        'royalty-free, non-exclusive, sublicensable license',
    ];

    private const HOUSE_MARKERS = [
        'BLUE FAIRY DEVELOPMENT LLC',
        'END USER LICENSE AGREEMENT',
        'It is licensed, not sold.',
    ];

    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function licencePath(): string
    {
        $found = glob(self::packageRoot() . '/LICEN[CS]E*') ?: [];
        self::assertCount(
            1,
            $found,
            'expected exactly one licence file in the package root, found: '
            . (implode(', ', array_map('basename', $found)) ?: 'none')
        );

        return $found[0];
    }

    public function testAProprietaryPackageShipsALicenceFile(): void
    {
        $path = self::licencePath();
        self::assertFileExists($path);
        self::assertGreaterThan(
            1000,
            (int) filesize($path),
            'the licence file is too short to be terms — a stub is not a licence'
        );
    }

    public function testTheComposerDeclarationAndTheShippedTextAgree(): void
    {
        $composer = json_decode((string) file_get_contents(self::packageRoot() . '/composer.json'), true);
        self::assertIsArray($composer);

        $declared = (array) ($composer['license'] ?? []);
        self::assertSame(
            ['proprietary'],
            $declared,
            'composer.json no longer declares this package proprietary; if that is deliberate the '
            . 'shipped licence text has to change with it, and this test is where you say so'
        );

        $text = (string) file_get_contents(self::licencePath());
        foreach (self::OPEN_SOURCE_GRANTS as $grant) {
            self::assertStringNotContainsStringIgnoringCase(
                $grant,
                $text,
                sprintf(
                    'the shipped licence contains "%s" while composer.json says proprietary. '
                    . 'A merchant reading vendor/ is told they may redistribute this.',
                    $grant
                )
            );
        }
    }

    public function testTheShippedTextIsTheHouseInstrument(): void
    {
        $text = (string) file_get_contents(self::licencePath());
        foreach (self::HOUSE_MARKERS as $marker) {
            self::assertStringContainsString(
                $marker,
                $text,
                sprintf('the licence does not carry "%s", so it is not the house EULA', $marker)
            );
        }
    }

    public function testTheLicenceNamesThisProductAndNotAnother(): void
    {
        $text = (string) file_get_contents(self::licencePath());

        self::assertStringContainsString(
            'Jira Integration for Adobe Commerce',
            $text,
            'the licence does not name this product'
        );

        // It is derived from the Printify EULA. A derivation that kept the
        // source product's name would be a licence for a different product,
        // shipped inside this one, and it would read as correct.
        self::assertStringNotContainsString(
            'Printify',
            $text,
            'the licence still names Printify — the derivation did not finish, and every '
            . 'substantive clause looks right regardless'
        );

        self::assertStringContainsString(
            'BlueFairy_JiraHyva',
            $text,
            'the Software definition does not list this package - the licence is shared across the set, so\n            // a derivation that dropped a package name licenses two of three'
        );
    }

    /**
     * The controls. Each assertion above is an assertion about prose, and prose
     * assertions are the ones that quietly cannot fail — so each predicate is
     * run here against a specimen chosen to make it fire.
     */
    public function testEachPredicateFiresOnAPlantedSpecimen(): void
    {
        $osl = 'Open Software License ("OSL") v. 3.0' . "\n"
            . 'Licensor grants You a worldwide, royalty-free, non-exclusive, sublicensable license';

        $hits = 0;
        foreach (self::OPEN_SOURCE_GRANTS as $grant) {
            if (stripos($osl, $grant) !== false) {
                $hits++;
            }
        }
        self::assertSame(
            2,
            $hits,
            'the open-source-grant predicates do not match the exact OSL-3.0 header this package '
            . 'shipped until 1.1.1 — so their absence from the real file proves nothing'
        );

        $gpl = 'GNU GENERAL PUBLIC LICENSE Version 3';
        self::assertTrue(
            stripos($gpl, self::OPEN_SOURCE_GRANTS[1]) !== false,
            'the GPL predicate does not match a GPL header, and three packages in this estate ship one'
        );

        // And the house markers must be absent from an open licence, or
        // testTheShippedTextIsTheHouseInstrument would pass on OSL-3.0 too.
        foreach (self::HOUSE_MARKERS as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $osl,
                sprintf('"%s" appears in OSL-3.0 text, so it does not discriminate', $marker)
            );
        }
    }
}
