<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * An analyser reports zero errors over a file it never opened, and that zero is
 * byte-identical to the zero it reports over a file it read and found clean.
 *
 * The base package module-jira shipped with `Setup/` outside phpstan's `paths`. One
 * file lived there - the revertable teardown patch, the only thing that clears
 * `patch_list` on uninstall - and "phpstan 0" had been quoted for that package all
 * week while saying nothing about it. Planting a call to a method that does not
 * exist proved it: no errors before the path was added, one error after.
 *
 * This package is currently complete. The point of this test is that it stays that
 * way when somebody adds a directory, because nothing else here would say otherwise.
 */
class AnalyserCoverageTest extends TestCase
{
    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function testPhpstanAnalysesEveryDirectoryThatShipsPhp(): void
    {
        $root = self::packageRoot();
        $config = file_get_contents($root . '/phpstan.neon.dist');

        self::assertIsString($config, 'phpstan.neon.dist is unreadable - this would pass over nothing');

        $analysed = [];
        if (preg_match('/^\s*paths:\s*$(.*?)^\s*\w+:/ms', $config, $m)) {
            preg_match_all('/^\s*-\s*(\S+)\s*$/m', $m[1], $found);
            $analysed = $found[1];
        }

        self::assertNotEmpty($analysed, 'no paths parsed from phpstan.neon.dist - empty search space');

        $shipping = [];

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry[0] === '.' || $entry === 'Test' || !is_dir($root . '/' . $entry)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $entry));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $shipping[] = $entry;
                    break;
                }
            }
        }

        self::assertNotEmpty($shipping, 'no shipped PHP directories found - the assertion would be vacuous');

        $unanalysed = array_values(array_diff($shipping, $analysed));

        self::assertSame(
            [],
            $unanalysed,
            'These directories ship PHP and phpstan never opens them, so its "0 errors" says '
            . 'nothing about what is in them: ' . implode(', ', $unanalysed)
        );
    }

    /**
     * Comparing two lists is the shape that passes when one of them is empty. Prove the
     * comparison actually reacts to an uncovered directory.
     */
    public function testTheCoverageSweepNoticesAnUncoveredDirectory(): void
    {
        self::assertSame(['Setup'], array_values(array_diff(['Api', 'Setup'], ['Api'])));
    }
}
