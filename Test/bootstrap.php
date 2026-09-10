<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 *
 * Unit-test and static-analysis bootstrap for module-jira-hyva.
 *
 * Adapted 2026-09-09 from module-packagist-provisioner's bootstrap, which is the
 * house reference. module-jira-hyva shipped v1.0.4 carrying 21 test files and NO way
 * to run them: no phpunit.xml.dist, no phpcs.xml.dist, no phpstan.neon.dist. The
 * numbered lessons below were paid for in that sibling package and apply here
 * unchanged.
 *
 * MAGENTO_ROOT must point at a Magento installation, because these tests mock
 * Magento classes and phpstan reflects them. Four things matter here, and each
 * one cost something to learn.
 *
 * 1. LOAD vendor/autoload.php, NEVER app/bootstrap.php.
 *    Magento's app/bootstrap.php calls stream_wrapper_unregister('phar') at
 *    line 12. phpstan runs FROM a .phar, so the moment this file loaded
 *    Magento's bootstrap, phpstan's own worker could no longer read its own
 *    archive. It died with
 *        Class "Symfony\Component\String\UnicodeString" not found
 *    from inside its error RENDERER - a crash while reporting a crash, naming
 *    a class that has nothing to do with anything. The real line, several
 *    screens down, was "Unable to find the wrapper phar". Exit code 255, no
 *    findings, and it looks exactly like the analyser having an opinion about
 *    this module.
 *
 * 2. PREPEND the source tree. Composer maps BlueFairy\JiraHyva\ to
 *    the INSTALLED package in the Magento vendor/ directory. Without this, a
 *    run reads this repository's tests and exercises the last published
 *    release. Note that spl_autoload_register's own prepend flag is enough
 *    HERE, but is NOT enough after Magento's app/bootstrap.php has run -
 *    AutoloaderRegistry prepends Magento's loader ahead of everything.
 *
 * 3. REFUSE to run without compiled DI rather than guess. Magento generates
 *    extension-attribute interfaces at di:compile time. With generated/code
 *    empty, the synthesiser below would stand in an EMPTY
 *    ProductExtensionInterface, every extension-attribute method would vanish,
 *    and the run would report errors that are really one missing directory.
 *
 * 4. Synthesise a class ONLY when composer cannot find a real file for it.
 *    A blanket "ends in Factory" rule once replaced six real, hand-written
 *    Magento core classes with empty shells in a sibling package, and the
 *    error count a release was being signed off against turned out to be a
 *    measurement of the bootstrap rather than of the code. findFile() first is
 *    what distinguishes a generated factory from a hand-written one.
 */

declare(strict_types=1);

$magentoRoot = getenv('MAGENTO_ROOT') ?: '';

if ($magentoRoot === '' || !is_file($magentoRoot . '/vendor/autoload.php')) {
    fwrite(
        STDERR,
        "MAGENTO_ROOT is not set to a Magento installation.\n"
        . "These tests mock Magento classes, so the framework has to be loadable:\n"
        . "  MAGENTO_ROOT=/path/to/magento vendor/bin/phpunit -c phpunit.xml.dist\n"
    );
    exit(1);
}

/** @var \Composer\Autoload\ClassLoader $composerLoader */
$composerLoader = require $magentoRoot . '/vendor/autoload.php';

$src = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($src): void {
    $prefix = 'BlueFairy\\JiraHyva\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $src . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);

$generated = $magentoRoot . '/generated/code/';

/**
 * M2MAP-283(b). WHAT THE SENTINEL PROVES, AND WHAT IT DOES NOT.
 *
 * The generic sentinel below proves only that SOMETHING was compiled on this
 * install. It cannot prove that THIS module was - a di:compile run from before
 * this package existed satisfies it perfectly - so it is a proxy, and it is
 * named as one rather than left to read like a guarantee.
 *
 * Where the package declares extension attributes OF ITS OWN, there is a
 * better instrument, and it is used first: every <extension_attributes for="X">
 * in etc/extension_attributes.xml requires a generated XExtensionInterface, and
 * that file existing proves this module's own contribution reached the
 * compiler. This package declares none today; the loop is data-driven so it
 * strengthens by itself the moment one is added, rather than needing someone to
 * notice.
 *
 * @param string $forType e.g. Magento\Catalog\Api\Data\ProductInterface
 * @return string the generated interface path that must exist for it
 */
function jiraBootstrapExtensionSentinel(string $forType): string
{
    $base = str_ends_with($forType, 'Interface') ? substr($forType, 0, -strlen('Interface')) : $forType;

    return str_replace('\\', '/', ltrim($base, '\\')) . 'ExtensionInterface.php';
}

$ownAttributes = [];
$attributesFile = dirname(__DIR__) . '/etc/extension_attributes.xml';
if (is_file($attributesFile)) {
    $attributesXml = simplexml_load_file($attributesFile);
    if ($attributesXml !== false) {
        foreach ($attributesXml->xpath('//extension_attributes') ?: [] as $declaration) {
            $for = (string) ($declaration['for'] ?? '');
            if ($for !== '') {
                $ownAttributes[$for] = jiraBootstrapExtensionSentinel($for);
            }
        }
    }
}

foreach ($ownAttributes as $forType => $sentinel) {
    if (!is_file($generated . $sentinel)) {
        fwrite(STDERR, sprintf(
            "\nREFUSING to run: this module's OWN extension attribute for %s has not been compiled.\n"
            . "Missing: %s\n"
            . "etc/extension_attributes.xml declares it, so a di:compile that included this module\n"
            . "would have produced it. Its absence means the install at %s was compiled WITHOUT\n"
            . "this module, and any accessor this package adds would be synthesised away.\n"
            . "Run:  cd %s && php -d memory_limit=-1 bin/magento setup:di:compile\n\n",
            $forType,
            $generated . $sentinel,
            $magentoRoot,
            $magentoRoot
        ));
        exit(1);
    }
}

// The proxy. Weaker than the loop above on purpose: it says DI was compiled at
// some point, not that it was compiled with this module in it.
foreach (['Magento/Catalog/Api/Data/ProductExtensionInterface.php'] as $sentinel) {
    if (!is_file($generated . $sentinel)) {
        fwrite(STDERR, sprintf(
            "\nREFUSING to run: %s is missing.\n"
            . "The Magento install at %s has no compiled DI AT ALL, so extension-attribute\n"
            . "interfaces would be synthesised empty and this run would measure phantoms.\n"
            . "(This is a proxy: it proves something was compiled, not that THIS module was.\n"
            . "The stronger check runs above when the package declares its own attributes.)\n"
            . "Run:  cd %s && php -d memory_limit=-1 bin/magento setup:di:compile\n\n",
            $generated . $sentinel,
            $magentoRoot,
            $magentoRoot
        ));
        exit(1);
    }
}

spl_autoload_register(static function (string $class) use ($generated): void {
    $file = $generated . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/**
 * Should the bootstrap invent a stand-in for this class? A class composer can
 * find is never synthesised; it is loaded.
 *
 * @param string $class
 * @param \Composer\Autoload\ClassLoader $loader
 * @return bool
 */
function jiraBootstrapShouldSynthesise(string $class, \Composer\Autoload\ClassLoader $loader): bool
{
    $isFactory   = str_ends_with($class, 'Factory');
    $isExtension = str_ends_with($class, 'ExtensionInterface');
    $isExtData   = str_ends_with($class, 'Extension') && !$isExtension;

    if (!$isFactory && !$isExtension && !$isExtData) {
        return false;
    }

    return $loader->findFile($class) === false;
}

spl_autoload_register(static function (string $class) use ($composerLoader): void {
    if (class_exists($class, false) || interface_exists($class, false)) {
        return;
    }

    if (!jiraBootstrapShouldSynthesise($class, $composerLoader)) {
        $realFile = $composerLoader->findFile($class);
        if ($realFile !== false) {
            require $realFile;
        }
        return;
    }

    $isExtension = str_ends_with($class, 'ExtensionInterface');
    $isExtData   = str_ends_with($class, 'Extension') && !$isExtension;

    $pos       = strrpos($class, '\\');
    $namespace = $pos === false ? '' : substr($class, 0, $pos);
    $shortName = $pos === false ? $class : substr($class, $pos + 1);
    $nsDecl    = $namespace === '' ? '' : sprintf('namespace %s;', $namespace);

    if ($isExtension) {
        eval(sprintf('%s interface %s {}', $nsDecl, $shortName));
        return;
    }

    if ($isExtData) {
        $iface      = $class . 'Interface';
        $implements = (interface_exists($iface) || class_exists($iface)) ? ' implements \\' . $iface : '';
        eval(sprintf('%s class %s%s {}', $nsDecl, $shortName, $implements));
        return;
    }

    eval(sprintf(
        '%s class %s { public function create(array $data = []) { return null; } }',
        $nsDecl,
        $shortName
    ));
});
