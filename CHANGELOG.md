# Changelog — BlueFairy_JiraHyva

## 1.0.0 — unreleased

First release. Split out of `bluefairy/module-jira` 1.1.x, which shipped Alpine and
Tailwind templates in the base package with no Luma versions and no companion — so a
Luma store rendered markup nothing interpreted and classes nothing emitted, and a Hyvä
store got templates the theme's build did not know to scan. (M2MAP-359)

Moved here from the base package, unchanged except for the namespace:

- `view/frontend/templates/ticket/new.phtml`
- `view/frontend/templates/account/tickets.phtml`
- `view/frontend/tailwind/module.css`
- `Observer/RegisterModuleForHyvaConfig` and its `hyva_config_generate_before` subscription

New here:

- Two `referenceBlock` layout overrides — **template only**, so the block name, container,
  page layout, title and view model stay declared once, in the base package.
- `etc/module.xml` sequencing after `BlueFairy_Jira` and `Hyva_Theme`.
- The observer's first unit test in either package. Its previous coverage asserted the
  event *subscription* existed, which proves it is wired and not that it does anything —
  a subscription to a method that returns early is indistinguishable from no subscription.
