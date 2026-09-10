<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * What this package DECLARES, as opposed to what it does.
 *
 * A `-hyva` companion is almost entirely declaration: two layout overrides, two
 * templates, one observer and a composer constraint. None of it is reachable by a
 * behavioural test, and every one of them fails SILENTLY when it is wrong -
 * `<referenceBlock name="...">` naming a block that does not exist is ignored by
 * Magento with no error, no warning and no log line. The storefront simply keeps
 * rendering the base template and the merchant reports "the Hyva styling isn't
 * applied", which reads like a CSS problem.
 */
class HyvaOverridesTest extends TestCase
{
    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * The base package, found by a MARKER FILE rather than by counting directories.
     *
     * `dirname(__DIR__, N)` with N one too large does not fail - it WIDENS, and a
     * sweep then silently covers the whole estate. Anchor on something only the
     * intended directory has.
     */
    private static function basePackageRoot(): string
    {
        $sibling = dirname(self::packageRoot()) . '/module-jira';
        if (!is_file($sibling . '/etc/adminhtml/menu.xml')
            || !is_file($sibling . '/registration.php')
        ) {
            throw new RuntimeException(
                'the base package was not found at ' . $sibling . ' by its marker files, so the '
                . 'cross-package assertions below would pass over an empty search space'
            );
        }
        return $sibling;
    }

    /**
     * @return array<string, array{0: string, 1: string}> handle => [block name, template]
     */
    public static function overrideProvider(): array
    {
        return [
            'ticket form'  => ['bluefairy.jira.ticket.new', 'BlueFairy_JiraHyva::ticket/new.phtml'],
            'ticket list'  => ['bluefairy.jira.account.tickets', 'BlueFairy_JiraHyva::account/tickets.phtml'],
        ];
    }

    /**
     * THE ASSERTION THAT MATTERS. Every block this package re-templates must be a
     * block the BASE package actually declares. If the base ever renames one, this
     * override stops applying and nothing anywhere reports it.
     *
     * @dataProvider overrideProvider
     */
    public function testEveryOverriddenBlockExistsInTheBasePackage(string $blockName, string $template): void
    {
        $declared = [];
        foreach (glob(self::basePackageRoot() . '/view/frontend/layout/*.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            self::assertNotFalse($xml, "base layout did not parse: {$file}");
            foreach ($xml->xpath('//block') ?: [] as $block) {
                $declared[] = (string) $block['name'];
            }
        }

        if ($declared === []) {
            throw new RuntimeException('the base package declares no blocks at all - empty search space');
        }

        self::assertContains(
            $blockName,
            $declared,
            "this package re-templates '{$blockName}', which BlueFairy_Jira does not declare. "
            . 'Magento ignores a referenceBlock naming a block that does not exist - silently - '
            . 'so the storefront keeps the base template and the defect reads as a styling bug.'
        );
    }

    /**
     * The control for the sweep above: a name the base cannot possibly declare must
     * be rejected, or `assertContains` over a long list proves nothing.
     */
    public function testTheBlockSweepRejectsANameTheBaseDoesNotDeclare(): void
    {
        $declared = [];
        foreach (glob(self::basePackageRoot() . '/view/frontend/layout/*.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            foreach (($xml ?: new \SimpleXMLElement('<r/>'))->xpath('//block') ?: [] as $block) {
                $declared[] = (string) $block['name'];
            }
        }

        self::assertNotContains('bluefairy.jira.no.such.block', $declared);
    }

    /**
     * @dataProvider overrideProvider
     */
    public function testTheOverrideNamesATemplateThisPackageShips(string $blockName, string $template): void
    {
        [$module, $path] = explode('::', $template);
        self::assertSame('BlueFairy_JiraHyva', $module, 'the override points at another module');
        self::assertFileExists(
            self::packageRoot() . '/view/frontend/templates/' . $path,
            "the layout names {$template} and this package does not ship it"
        );
    }

    /**
     * @dataProvider overrideProvider
     */
    public function testTheLayoutFileActuallyCarriesTheOverride(string $blockName, string $template): void
    {
        $found = [];
        foreach (glob(self::packageRoot() . '/view/frontend/layout/*.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            self::assertNotFalse($xml, "layout did not parse: {$file}");
            foreach ($xml->xpath('//referenceBlock') ?: [] as $ref) {
                $found[(string) $ref['name']] = (string) $ref['template'];
            }
        }

        self::assertArrayHasKey($blockName, $found, 'no referenceBlock for this block');
        self::assertSame($template, $found[$blockName]);
    }

    /**
     * The sequence is load-bearing: Magento merges layout in module load order, so
     * this module's override can only replace the base template if BlueFairy_Jira
     * loads first. Without it the override wins on some installs and loses on others.
     */
    public function testTheModuleSequencesAfterTheBasePackageAndHyva(): void
    {
        $xml = simplexml_load_file(self::packageRoot() . '/etc/module.xml');
        self::assertNotFalse($xml, 'etc/module.xml did not parse');

        $sequence = [];
        foreach ($xml->xpath('//sequence/module') ?: [] as $module) {
            $sequence[] = (string) $module['name'];
        }

        self::assertContains('BlueFairy_Jira', $sequence, 'the base package is not sequenced before this one');
        self::assertContains('Hyva_Theme', $sequence, 'Hyva_Theme is not sequenced before this one');
    }

    public function testTheHyvaTailwindRegistrationIsWiredHere(): void
    {
        self::assertFileExists(
            self::packageRoot() . '/view/frontend/tailwind/module.css',
            'the Tailwind source file Hyva imports is missing'
        );

        $events = simplexml_load_file(self::packageRoot() . '/etc/frontend/events.xml');
        self::assertNotFalse($events, 'etc/frontend/events.xml did not parse');

        $observed = [];
        foreach ($events->xpath('//event') ?: [] as $event) {
            foreach ($event->xpath('observer') ?: [] as $observer) {
                $observed[(string) $event['name']] = (string) $observer['instance'];
            }
        }

        self::assertArrayHasKey('hyva_config_generate_before', $observed);
        self::assertTrue(
            class_exists($observed['hyva_config_generate_before']),
            'the observer is declared but its class does not exist: '
            . $observed['hyva_config_generate_before']
        );
    }

    /**
     * A constraint is a claim about EVERY version it admits.
     */
    public function testNoRequirementIsUnbounded(): void
    {
        $composer = json_decode(file_get_contents(self::packageRoot() . '/composer.json'), true);
        self::assertIsArray($composer);

        $require = $composer['require'] ?? [];
        self::assertNotEmpty($require, 'no requirements found - the search space is empty');

        $unbounded = array_keys(array_filter($require, static fn ($v): bool => trim((string) $v) === '*'));
        self::assertSame([], $unbounded, 'unbounded constraints: ' . implode(', ', $unbounded));

        self::assertArrayHasKey(
            'bluefairy/module-jira',
            $require,
            'a companion package that does not require its base can be installed alone, and then '
            . 'every block it re-templates is a block nothing declares'
        );
    }
}
