<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Test\Unit\Observer;

use BlueFairy\JiraHyva\Observer\RegisterModuleForHyvaConfig;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

/**
 * The observer that makes Hyvä's Tailwind build scan this module.
 *
 * IT HAD NO TEST IN EITHER PACKAGE until this one. M2MAP-359 measured what its
 * absence costs on a running store - 43 of 110 distinct classes emitting no CSS,
 * `py-2.5` giving the Submit Ticket button padding-top 0px - and the cure was
 * checked by asserting the EVENT SUBSCRIPTION existed. That proves the observer is
 * wired, not that it does anything. Both halves are needed: a subscription to a
 * method that returns early is indistinguishable from no subscription at all.
 */
class RegisterModuleForHyvaConfigTest extends TestCase
{
    private const ROOT = '/var/www/magento';

    private ComponentRegistrar $registrar;

    protected function setUp(): void
    {
        // Magento defines BP at bootstrap; Test/bootstrap.php deliberately does not
        // load app/bootstrap.php, so define it here if it is absent. It is a
        // constant, so this is once per process and every expectation below is
        // derived from BP rather than from the literal.
        if (!defined('BP')) {
            define('BP', self::ROOT);
        }

        $this->registrar = $this->createMock(ComponentRegistrar::class);
    }

    private function subject(): RegisterModuleForHyvaConfig
    {
        return new RegisterModuleForHyvaConfig($this->registrar);
    }

    private function eventCarrying(?DataObject $config): Observer
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('config')->willReturn($config);

        return $observer;
    }

    public function testItAppendsThisModulesPathRelativeToTheMagentoRoot(): void
    {
        $modulePath = constant('BP') . '/vendor/bluefairy/module-jira-hyva';
        $this->registrar->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'BlueFairy_JiraHyva')
            ->willReturn($modulePath);

        $config = new DataObject();
        $this->subject()->execute($this->eventCarrying($config));

        self::assertSame(
            [['src' => 'vendor/bluefairy/module-jira-hyva']],
            $config->getData('extensions'),
            'the path Hyva receives must be ROOT-RELATIVE. An absolute one is written into '
            . "the theme's generated source list and resolves on the build machine and "
            . 'nowhere else.'
        );
    }

    /**
     * It REGISTERS THIS PACKAGE, not the base one. The class was moved here from
     * BlueFairy_Jira, and a MODULE_NAME left pointing at the old package would ask
     * Hyva to scan a directory whose templates no longer carry a Tailwind class -
     * and would not scan the directory that does.
     */
    public function testItRegistersThisPackageAndNotTheBaseModule(): void
    {
        $this->registrar->expects($this->once())
            ->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'BlueFairy_JiraHyva')
            ->willReturn(constant('BP') . '/app/code/BlueFairy/JiraHyva');

        $this->subject()->execute($this->eventCarrying(new DataObject()));
    }

    public function testItPreservesExtensionsAlreadyRegisteredByOtherModules(): void
    {
        $this->registrar->method('getPath')->willReturn(constant('BP') . '/app/code/BlueFairy/JiraHyva');

        $config = new DataObject(['extensions' => [['src' => 'app/code/Other/Module']]]);
        $this->subject()->execute($this->eventCarrying($config));

        self::assertSame(
            [
                ['src' => 'app/code/Other/Module'],
                ['src' => 'app/code/BlueFairy/JiraHyva'],
            ],
            $config->getData('extensions'),
            'appending must not drop what another module registered - Hyva calls every '
            . 'observer on the same config object'
        );
    }

    public function testItDoesNothingWhenTheEventCarriesNoConfig(): void
    {
        $this->registrar->expects($this->never())->method('getPath');

        $this->subject()->execute($this->eventCarrying(null));
    }

    /**
     * An unregistered module returns null from getPath(). Appending anyway would put
     * `['src' => '']` into the list, and an empty source path is not "nothing" - it
     * is the THEME ROOT, so the build would scan everything.
     */
    public function testItDoesNothingWhenTheModulePathIsUnknown(): void
    {
        $this->registrar->method('getPath')->willReturn(null);

        $config = new DataObject();
        $this->subject()->execute($this->eventCarrying($config));

        self::assertNull($config->getData('extensions'));
    }
}
