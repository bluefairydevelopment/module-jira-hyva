<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;

/**
 * The three Jira packages live in THREE SEPARATE GIT REPOSITORIES - unlike
 * order-comments and recipes, which are monorepos. Nothing therefore forces their
 * version constraints to agree, and they drifted: this package required the base at
 * `^1.2` while the companion GraphQL package required `^1.1`, and the release was
 * being cut as 1.1.0.
 *
 * `^1.2` means `>=1.2.0 <2.0.0`. It does not admit 1.1.0. Tagging the set at 1.1.0
 * would have produced a companion that composer simply refuses to resolve - not a
 * subtle misbehaviour, an uninstallable package - and the only place that would have
 * shown up is an install on a real box.
 *
 * The invariant that catches it without needing the other repositories present: the
 * three ship as a SET at one version, so this package's constraint on the base must
 * admit THIS package's own released version. Both numbers live in this repository, so
 * the check is self-contained and cannot be defeated by a sibling repo being absent.
 */
class VersionConstraintsAdmitThisReleaseTest extends TestCase
{
    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * The version this package is being released as, read from the top of its own
     * CHANGELOG rather than hardcoded here - so bumping the release moves the
     * expectation with it and this test keeps asking the same question.
     */
    private static function releaseVersion(): string
    {
        $changelog = file_get_contents(self::packageRoot() . '/CHANGELOG.md');

        self::assertIsString($changelog, 'CHANGELOG.md is unreadable - this test would have nothing to check');

        if (!preg_match('/^##\s+(\d+\.\d+\.\d+)/m', $changelog, $m)) {
            self::fail(
                'No version heading found at the top of CHANGELOG.md. Either the format changed '
                . 'and this test stopped checking anything, or the release has no entry.'
            );
        }

        return $m[1];
    }

    public function testTheChangelogStatesAVersion(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', self::releaseVersion());
    }

    public function testTheConstraintOnTheBasePackageAdmitsThisRelease(): void
    {
        $composer = json_decode(file_get_contents(self::packageRoot() . '/composer.json'), true);

        self::assertIsArray($composer, 'composer.json did not parse');

        $constraint = $composer['require']['bluefairy/module-jira'] ?? null;

        self::assertIsString(
            $constraint,
            'This package does not require bluefairy/module-jira at all. It is a template '
            . 'override package for it - without the requirement, composer will happily '
            . 'install it alone and every layout override will silently target nothing.'
        );

        $release = self::releaseVersion();

        self::assertTrue(
            self::caretAdmits($constraint, $release),
            sprintf(
                'This package is released as %s and requires the base package at "%s", which '
                . 'does not admit %s. The three Jira packages are versioned as a set, so '
                . 'composer would refuse to resolve this release at all.',
                $release,
                $constraint,
                $release
            )
        );
    }

    /**
     * An absence-shaped assertion passes when the comparison is broken rather than when
     * the constraint is right. Prove the comparison rejects the constraint we actually
     * shipped, and accepts the one that replaced it.
     */
    public function testTheConstraintCheckRejectsTheConstraintThatWasWrong(): void
    {
        self::assertFalse(self::caretAdmits('^1.2', '1.1.0'), '^1.2 must NOT admit 1.1.0');
        self::assertTrue(self::caretAdmits('^1.1', '1.1.0'), '^1.1 must admit 1.1.0');
        self::assertTrue(self::caretAdmits('^1.1', '1.9.3'), '^1.1 must admit later minors');
        self::assertFalse(self::caretAdmits('^1.1', '2.0.0'), '^1.1 must not admit the next major');
    }

    /**
     * Minimal caret semantics, which is all these constraints use: ^X.Y.Z admits
     * >=X.Y.Z and <(X+1).0.0.
     */
    private static function caretAdmits(string $constraint, string $version): bool
    {
        if (!preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', trim($constraint), $c)) {
            self::fail("constraint \"{$constraint}\" is not the simple caret form this test understands");
        }

        [$major, $minor, $patch] = [(int) $c[1], (int) $c[2], (int) ($c[3] ?? 0)];
        [$vMajor, $vMinor, $vPatch] = array_map('intval', explode('.', $version));

        if ($vMajor !== $major) {
            return false;
        }

        return [$vMinor, $vPatch] >= [$minor, $patch];
    }
}
