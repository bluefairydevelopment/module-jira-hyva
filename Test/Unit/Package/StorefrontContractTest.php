<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * M2MAP-401 and M2MAP-403, asserted HERE and not only in the base package.
 *
 * The base's StorefrontContractTest sweeps this package too - but it SKIPS when the
 * sibling checkout is absent, which is exactly the state of a CI job or a merchant
 * install that has only one of the two. A cross-package sweep that can skip is not
 * this package's gate. See [[a-cure-that-covered-one-of-n-places]]: the assertion
 * has to live where the code lives.
 *
 * -403 These two templates hardcoded `fetch('/graphql')` three times while resolving
 *      all three of their page links through $block->getUrl() in the same files. The
 *      M2MAP-374 cure swept the base package's controllers and stopped there, and the
 *      base's Luma templates were newly written and got it right by construction - so
 *      the split left the defect in the one package nothing re-read.
 * -401 Every operation these templates call is declared only in
 *      bluefairy/module-jira-graph-ql. Without it both pages render and do nothing.
 */
class StorefrontContractTest extends TestCase
{
    private const GRAPHQL_OPERATIONS = ['createJiraTicket', 'jiraEligibleOrders', 'jiraSubmissions'];

    /**
     * A single leading slash is a path and is only correct at the domain root. `//` is
     * a protocol-relative host, excluded deliberately.
     */
    private const ROOT_RELATIVE_URL = '/([\'"])\/(?!\/)[a-z][a-z0-9_\-\/]*\1/i';

    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return list<string> */
    private static function templates(): array
    {
        $found = glob(self::packageRoot() . '/view/frontend/templates/*/*.phtml') ?: [];
        if (count($found) < 2) {
            throw new RuntimeException('expected both storefront templates under '
                . self::packageRoot() . '/view/frontend/templates; found ' . count($found)
                . ' - a sweep over that would report clean having examined almost nothing');
        }

        return array_values($found);
    }

    private static function read(string $path): string
    {
        $text = @file_get_contents($path);
        if ($text === false || trim($text) === '') {
            throw new RuntimeException("{$path} is missing or empty");
        }

        return $text;
    }

    public function testNeitherTemplateHardcodesARootRelativeUrl(): void
    {
        $offenders = [];
        foreach (self::templates() as $template) {
            if (preg_match_all(self::ROOT_RELATIVE_URL, self::read($template), $m)) {
                foreach ($m[0] as $literal) {
                    $offenders[] = basename($template) . ': ' . $literal;
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'root-relative URL literal(s) in a shipped template: ' . implode('; ', $offenders)
            . '. On a store served from a subdirectory every page link in these files still '
            . 'works and every data call 404s. Resolve through $block->getUrl(\'graphql\') as '
            . 'the base package\'s Luma templates do (M2MAP-403).'
        );
    }

    /** The control: the pattern must catch the three literals the cure removed. */
    public function testTheRootRelativeSweepFindsTheLiteralsItRemoved(): void
    {
        preg_match_all(self::ROOT_RELATIVE_URL, "const res = await fetch('/graphql', {", $m);

        self::assertSame(["'/graphql'"], $m[0], 'the predicate must catch the M2MAP-403 literal');
    }

    /** And must not fire on the corrected form, or the cure is to write worse code. */
    public function testTheRootRelativeSweepIgnoresTheCorrectedForm(): void
    {
        preg_match_all(
            self::ROOT_RELATIVE_URL,
            "var JIRA_GRAPHQL_URL = '<?= \$escaper->escapeJs(\$block->getUrl('graphql')) ?>';\n"
            . "const res = await fetch(JIRA_GRAPHQL_URL, { method: 'POST' })\n",
            $m
        );

        self::assertSame([], $m[0]);
    }

    /**
     * The endpoint must be resolved, once, per template - and USED. A declared constant
     * nothing reads is the same silent nothing as a hardcoded path.
     */
    public function testEachTemplateResolvesTheEndpointThroughTheFrameworkAndUsesIt(): void
    {
        foreach (self::templates() as $template) {
            $markup = self::read($template);

            self::assertStringContainsString(
                "\$block->getUrl('graphql')",
                $markup,
                basename($template) . ' does not resolve the GraphQL endpoint through the framework'
            );
            self::assertGreaterThan(
                0,
                substr_count($markup, 'fetch(JIRA_GRAPHQL_URL'),
                basename($template) . ' declares the resolved endpoint and never fetches with it'
            );
            self::assertSame(
                substr_count($markup, 'await fetch('),
                substr_count($markup, 'fetch(JIRA_GRAPHQL_URL'),
                basename($template) . ' has a fetch() that does not use the resolved endpoint'
            );
        }
    }

    /**
     * M2MAP-401. Each template calls operations only the GraphQL package declares, so
     * each must consult the base package's availability flag - or a store without that
     * package gets a page that renders and silently does nothing.
     */
    public function testEachTemplateConsultsTheGraphQlAvailabilityFlag(): void
    {
        foreach (self::templates() as $template) {
            $markup = self::read($template);

            $calls = array_values(array_filter(
                self::GRAPHQL_OPERATIONS,
                static fn (string $op): bool => str_contains($markup, $op)
            ));
            if ($calls === []) {
                continue;
            }

            self::assertStringContainsString(
                'graphql_availability',
                $markup,
                basename($template) . ' calls ' . implode(', ', $calls) . ' without consulting '
                . 'graphql_availability (M2MAP-401)'
            );
            self::assertStringContainsString(
                'GraphQlAvailability::GRAPHQL_PACKAGE',
                $markup,
                basename($template) . ' consults the flag but has no fallback package name, so a '
                . 'missing layout argument would name nothing in the notice'
            );
        }
    }

    /**
     * `php -l` PASSES on a template whose PHP was inserted after the closing `?>` - it
     * is valid inline HTML and the page prints the source. That happened while curing
     * M2MAP-401 and the linter said nothing, because a linter answers "is this a valid
     * PHP file", not "is this code". Tokenise instead.
     */
    public function testNeitherTemplateLeaksPhpAsInlineHtml(): void
    {
        foreach (self::templates() as $template) {
            self::assertSame(
                [],
                self::phpLeakedAsHtml(self::read($template)),
                basename($template) . ' contains PHP outside a PHP block; the page prints it'
            );
        }
    }

    /** The control: the exact shape that passed `php -l` must be caught. */
    public function testThePhpLeakDetectorCatchesTheShapeThatPassedPhpL(): void
    {
        self::assertNotSame(
            [],
            self::phpLeakedAsHtml("<?php\n\$x = 1;\n?>\n\$graphQl = \$block->getData('x');\n")
        );
        self::assertSame(
            [],
            self::phpLeakedAsHtml("<?php\n\$graphQl = \$block->getData('x');\n?>\n<div>ok</div>\n")
        );
    }

    /** @return list<string> */
    private static function phpLeakedAsHtml(string $source): array
    {
        $html = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_INLINE_HTML) {
                $html .= $token[1];
            }
        }

        return preg_match_all('/^\s*\$[a-z_][a-z0-9_]*\s*(=[^=]|->)/im', $html, $m) ? $m[0] : [];
    }
}
