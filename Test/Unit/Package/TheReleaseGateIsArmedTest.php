<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * M2MAP-598 / M2MAP-546. NOTHING RAN THIS PACKAGE'S GATES BEFORE A TAG.
 *
 * What stood between a red suite and a published tag was an untracked copy of the
 * shared ~/Sites/Modules/.git-hooks/pre-push that checked only whether CHANGELOG.md
 * carried a version heading, and only warned. Untracked means "no hook" is the
 * default state of every fresh clone.
 *
 * The same file sits in all three Jira repositories (module-jira, -graph-ql,
 * -hyva), as does this test. It holds the facts that decide whether the gate
 * exists: the hook is TRACKED, it RUNS THE GATES on a tag push, THIS CLONE is
 * wired to it, and it cannot reach a merchant - in a single-package repository
 * the root IS the package, so that last one depends on `.githooks/ export-ignore`
 * and is asserted on the real archive. The branch and tag rules are RUN in
 * throwaway repositories, not read. Pushing a real tag over a planted red test is
 * the one control a test cannot do; it is recorded in the hook header, the 1.1.4
 * CHANGELOG and M2MAP-598.
 */
class TheReleaseGateIsArmedTest extends TestCase
{
    /** Test/Unit/Package -> the repository root, three levels up. */
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function git(string $args): string
    {
        $out = [];
        $status = 0;
        // Reviewed and kept. This test's subject IS the git repository and it runs
        // standalone; nothing outside this class reaches the command, and Test/ is
        // export-ignored, so this never leaves the development tree.
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec('cd ' . escapeshellarg(self::root()) . ' && git ' . $args . ' 2>&1', $out, $status);
        if ($status !== 0) {
            throw new RuntimeException("git {$args} exited {$status}: " . implode("\n", $out));
        }
        return implode("\n", $out);
    }

    private static function hookPath(): string
    {
        return self::root() . '/.githooks/pre-push';
    }

    public function testTheHookIsTrackedAndExecutable(): void
    {
        self::assertSame(
            '.githooks/pre-push',
            trim(self::git('ls-files -- .githooks/pre-push')),
            '.githooks/pre-push is not tracked, so every fresh clone has no release gate'
        );
        self::assertTrue(is_executable(self::hookPath()), 'the hook is not executable, so git will not run it');
    }

    /** Over the EXECUTABLE lines: the header names every gate, so a whole-file match cannot fail. */
    public function testTheHookRunsEveryGateOnATagPush(): void
    {
        $code = self::executableLines((string) file_get_contents(self::hookPath()));
        self::assertStringContainsString('refs/tags/', $code);
        foreach (['phpunit', 'phpcs', 'phpstan', 'composer validate'] as $gate) {
            self::assertMatchesRegularExpression(
                '/^\s*gate \S+ .*' . preg_quote($gate, '/') . '/m',
                $code,
                "the hook no longer runs {$gate} through gate() on a tag push"
            );
        }
        self::assertStringContainsString('MAGENTO_ROOT', $code);
    }

    /**
     * phpstan FROM A COPY OF THE PHAR. Run in place it loads its own vendor/'s
     * autoloader and, against a different MAGENTO_ROOT, dies on a module
     * registered twice.
     */
    public function testPhpstanRunsFromACopiedPhar(): void
    {
        $code = self::executableLines((string) file_get_contents(self::hookPath()));
        self::assertMatchesRegularExpression('/^\s*cp "\$stan_src" "\$run_dir\/phpstan\.phar"/m', $code);
        self::assertMatchesRegularExpression('/^\s*gate phpstan .*php "\$run_dir\/phpstan\.phar" analyse/m', $code);
        self::assertStringNotContainsString('vendor/bin/phpstan', $code);
    }

    public function testACommentNamingAGateDoesNotCountAsRunningIt(): void
    {
        $code = self::executableLines("#!/usr/bin/env bash\n# runs phpunit, phpcs, phpstan, composer validate\nexit 0\n");
        self::assertStringNotContainsString('phpunit', $code, 'CONTROL: the comment stripper left a gate name');
        self::assertMatchesRegularExpression(
            '/^\s*gate \S+ .*phpunit/m',
            self::executableLines("    gate phpunit env A=b \$MAGENTO_ROOT/vendor/bin/phpunit -c phpunit.xml.dist\n"),
            'CONTROL: a real gate line was not recognised'
        );
    }

    /**
     * THE HOOK MUST NOT REACH A MERCHANT. With a file that must ship and a
     * directory that must not, so the listing is shown able to see both.
     */
    public function testThePublishedArchiveDoesNotContainTheHook(): void
    {
        $entries = array_values(array_filter(
            array_map('trim', explode("\n", self::git('archive HEAD | tar -t'))),
            static fn (string $e): bool => $e !== ''
        ));
        self::assertContains('registration.php', $entries, 'CONTROL: the archive is not the shipped file set');
        self::assertSame(
            [],
            array_values(array_filter($entries, static fn (string $e): bool => str_starts_with($e, 'Test/'))),
            'CONTROL: the archive ships Test/, so export-ignore is not being applied'
        );
        self::assertSame(
            [],
            array_values(array_filter($entries, static fn (string $e): bool => str_contains($e, 'githooks'))),
            'the published archive contains the release hook - .githooks/ is not export-ignored'
        );
    }

    public function testTheBranchRuleSeesEveryCommitOfAFirstPush(): void
    {
        self::assertSame(0, self::runBranchRule([
            ['src/a.php', 'one'],
            ['CHANGELOG.md', 'an entry'],
            ['src/b.php', 'two'],
        ]), 'a three-commit first push that DOES update CHANGELOG.md was rejected');
        self::assertSame(0, self::runBranchRule([['CHANGELOG.md', 'x']]), 'CONTROL: a single changelog commit was rejected');
        self::assertSame(1, self::runBranchRule([['src/a.php', 'one'], ['src/b.php', 'two']]), 'CONTROL: no changelog was accepted');
        self::assertSame(
            1,
            self::runBranchRule([['src/a.php', 'one'], ['docs/CHANGELOG.md', 'x']]),
            'a CHANGELOG.md other than the root one satisfied the rule'
        );
    }

    /**
     * Each refusal checked by its MESSAGE, in the hook's order; the last case gets
     * past all of them and stops at MAGENTO_ROOT, proving the earlier refusals are
     * not the hook refusing everything.
     */
    public function testATagIsRefusedUnlessHeadCleanAndInTheChangelog(): void
    {
        [$status, $err] = self::runTagRule('first', false, '9.9.9');
        self::assertSame(1, $status);
        self::assertStringContainsString('points at', $err);

        [$status, $err] = self::runTagRule('head', true, '9.9.9');
        self::assertSame(1, $status);
        self::assertStringContainsString('working tree has uncommitted', $err);

        [$status, $err] = self::runTagRule('head', false, '9.9.8');
        self::assertSame(1, $status);
        self::assertStringContainsString("CHANGELOG.md has no '## 9.9.9' heading", $err);

        [$status, $err] = self::runTagRule('head', false, '9.9.9');
        self::assertSame(1, $status);
        self::assertStringContainsString('MAGENTO_ROOT', $err, 'CONTROL: a clean HEAD tag in the changelog did not reach the gates');
        self::assertStringNotContainsString('heading', $err);
    }

    public function testThisCloneIsWiredToTheHook(): void
    {
        $out = [];
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec('cd ' . escapeshellarg(self::root()) . ' && git config core.hooksPath 2>/dev/null', $out);
        self::assertSame(
            '.githooks',
            trim(implode('', $out)),
            "This clone's core.hooksPath is not .githooks, so the tracked release gate is NOT "
            . "running and a tag push here is ungated. Fix, once:\n\n    git config core.hooksPath .githooks\n"
        );
    }

    private static function executableLines(string $shell): string
    {
        $kept = [];
        foreach (explode("\n", $shell) as $line) {
            $stripped = preg_replace('/^\s*#.*$/', '', $line);
            if (trim((string) $stripped) !== '') {
                $kept[] = $stripped;
            }
        }
        return implode("\n", $kept);
    }

    /** @param array<int, array{0: string, 1: string}> $files */
    private static function runBranchRule(array $files): int
    {
        $dir = sys_get_temp_dir() . '/jira-branch-rule-' . bin2hex(random_bytes(6));
        $script = self::repoPreamble($dir);
        foreach ($files as [$path, $body]) {
            $script[] = 'mkdir -p "$(dirname ' . escapeshellarg($path) . ')"';
            $script[] = 'printf %s ' . escapeshellarg($body) . ' >> ' . escapeshellarg($path);
            $script[] = 'git add ' . escapeshellarg($path) . ' && git commit -q -m c';
        }
        $script[] = 'sha=$(git rev-parse HEAD)';
        $script[] = 'set +e';
        $script[] = 'printf "refs/heads/main $sha refs/heads/main 0000000000000000000000000000000000000000\n" | bash '
            . escapeshellarg(self::hookPath()) . ' >/dev/null 2>&1';
        return self::runScript($script, $dir)[0];
    }

    /** @return array{0: int, 1: string} */
    private static function runTagRule(string $which, bool $dirty, string $changelogVersion): array
    {
        $dir = sys_get_temp_dir() . '/jira-tag-rule-' . bin2hex(random_bytes(6));
        $script = self::repoPreamble($dir);
        $script[] = 'printf one > a.txt && git add a.txt && git commit -q -m one';
        $script[] = 'first=$(git rev-parse HEAD)';
        $script[] = 'printf ' . escapeshellarg("# Changelog\n\n## {$changelogVersion} - today\n") . ' > CHANGELOG.md';
        $script[] = 'git add -A && git commit -q -m two';
        $script[] = 'sha=$' . ($which === 'first' ? '(git rev-parse $first)' : '(git rev-parse HEAD)');
        $script[] = $dirty ? 'printf x > untracked.txt' : 'true';
        $script[] = 'set +e';
        $script[] = 'printf "refs/tags/v9.9.9 $sha refs/tags/v9.9.9 0000000000000000000000000000000000000000\n" '
            . '| env -u MAGENTO_ROOT -u GATE_TOOLS_ROOT bash ' . escapeshellarg(self::hookPath())
            . ' >/dev/null 2>' . escapeshellarg($dir . '.err');
        return self::runScript($script, $dir, $dir . '.err');
    }

    /** @return list<string> */
    private static function repoPreamble(string $dir): array
    {
        return [
            'set -e',
            'mkdir -p ' . escapeshellarg($dir),
            'cd ' . escapeshellarg($dir),
            'git init -q .',
            'git config user.email gate@example.test',
            'git config user.name gate',
        ];
    }

    /**
     * @param list<string> $script
     * @return array{0: int, 1: string}
     */
    private static function runScript(array $script, string $dir, ?string $errFile = null): array
    {
        $script[] = 'status=$?';
        $script[] = 'cd /';
        $script[] = 'rm -rf ' . escapeshellarg($dir);
        $script[] = 'exit $status';

        $out = [];
        $status = 0;
        // Reviewed and kept: this asserts what a shell script does, which only running it establishes.
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec('bash -c ' . escapeshellarg(implode("\n", $script)), $out, $status);

        $stderr = '';
        if ($errFile !== null && is_file($errFile)) {
            $stderr = (string) file_get_contents($errFile);
            unlink($errFile);
        }
        return [$status, $stderr];
    }
}
