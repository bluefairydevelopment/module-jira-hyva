<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\JiraHyva\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Tell Hyvä's `hyva-config` generator where this module's Tailwind sources live.
 *
 * Mirrors the same observer in BlueFairy_FreeGiftHyva and BlueFairy_PrintifyCatalog.
 * Hyvä stores then pick up view/frontend/tailwind/module.css and the templates it
 * points at, so the utility classes used only in this module survive the build.
 *
 * M2MAP-359 measured what its absence costs. 43 of 110 distinct classes in this
 * module's two templates emitted no CSS on Hyvä/default, confirmed live rather
 * than from the stylesheet alone: py-2.5 gave the Submit Ticket button
 * padding-top 0px, gap-3 gave gap normal and flex-shrink-0 gave flex-shrink 1,
 * against compiled controls text-sm 14px and rounded-md 6px.
 *
 * NOTE the second half of that ticket, which this observer does NOT fix: these
 * templates use Alpine and Tailwind but live in the BASE module rather than a
 * -hyva companion, so on a Luma store neither exists. That needs a product
 * decision about splitting the package, not a patch.
 *
 * A "Tailwind safelist" comment in a template does nothing on its own — nothing
 * reads it. This observer is what makes the scanning happen.
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    private const MODULE_NAME = 'BlueFairy_JiraHyva';

    /**
     * @param ComponentRegistrar $componentRegistrar
     */
    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar
    ) {
    }

    /**
     * Append this module's root-relative path to the Hyvä extension list.
     *
     * @param Observer $event
     * @return void
     */
    public function execute(Observer $event): void
    {
        $config = $event->getData('config');
        if ($config === null) {
            return;
        }

        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);
        if ($path === null) {
            return;
        }

        // BP is Magento's root-path constant, defined at bootstrap; without it a
        // relative path cannot be formed, and a wrong one is worse than none.
        if (!defined('BP')) {
            return;
        }

        $root = (string) constant('BP');
        $extensions = $config->hasData('extensions') ? (array) $config->getData('extensions') : [];
        $extensions[] = ['src' => substr($path, strlen($root) + 1)];
        $config->setData('extensions', $extensions);
    }
}
