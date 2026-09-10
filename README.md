# BlueFairy Jira Integration — Hyvä

Hyvä storefront templates for [`bluefairy/module-jira`](../module-jira). It replaces the
base package's two Luma templates with Alpine/Tailwind versions and registers this
module's Tailwind sources with Hyvä's config generator.

## Install

```bash
composer require bluefairy/module-jira-hyva
bin/magento module:enable BlueFairy_JiraHyva
bin/magento setup:upgrade
```

## Then rebuild the theme's CSS — this step is not optional

```bash
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm run build
bin/magento cache:flush
```

**Registration is necessary and not sufficient.** Until the theme's Tailwind build runs,
the utility classes used only in this module's templates are absent from the compiled
stylesheet and every element carrying one renders as though the class were never written
— no console error, no 404, no failed request. M2MAP-359 measured it on a live Hyvä store:
43 of 110 distinct classes emitted no CSS, `py-2.5` gave the Submit Ticket button
`padding-top: 0px`, and `gap-3` gave `gap: normal`, against compiled controls at
`text-sm` 14px. It reads as "the styling is a bit off", which is why it survived review.

## What this package contains

| | |
| :--- | :--- |
| `view/frontend/templates/ticket/new.phtml` | Alpine ticket form |
| `view/frontend/templates/account/tickets.phtml` | Alpine ticket history |
| `view/frontend/layout/*.xml` | Two `referenceBlock` overrides — template only |
| `view/frontend/tailwind/module.css` | The source file Hyvä imports |
| `Observer/RegisterModuleForHyvaConfig` | Tells Hyvä where that file is |

It contains **no controllers, no models, no config and no ACL**. Every data path is the
base package's: the same `createJiraTicket` mutation, the same `jiraEligibleOrders` and
`jiraSubmissions` queries. Two skins over one submission path means one path to test.

## Two things that fail silently, and the tests that catch them

**A `referenceBlock` naming a block that does not exist is ignored by Magento** — no error,
no warning, no log line. The storefront keeps rendering the base template and the merchant
reports a styling problem. `Test/Unit/Package/HyvaOverridesTest` cross-checks every block
name here against the base package's own layout files.

**The `<sequence>` in `etc/module.xml` is load-bearing.** Magento merges layout in module
load order, so these overrides only win if `BlueFairy_Jira` loads first. Without the
sequence entry the result depends on `app/etc/config.php` ordering — it wins on one box
and loses on another.

## Gates

```bash
MAGENTO_ROOT=/path/to/magento /path/to/magento/vendor/bin/phpunit -c phpunit.xml.dist
MAGENTO_ROOT=/path/to/magento /path/to/magento/vendor/bin/phpstan analyse -c phpstan.neon.dist
/path/to/magento/vendor/bin/phpcs --standard=phpcs.xml.dist
```

`phpstan.neon.dist` carries **no** `ignoreErrors`. The base package has ten, each read
against the Magento source it names; this package has one shipped class and starts at a
true zero.
