# Changelog — BlueFairy_JiraHyva

## 1.0.0 — unreleased

First release. Split out of `bluefairy/module-jira` 1.1.x, which shipped Alpine and
Tailwind templates in the base package with no Luma versions and no companion — so a
Luma store rendered markup nothing interpreted and classes nothing emitted, and a Hyvä
store got templates the theme's build did not know to scan. (M2MAP-359)

Moved here from the base package, unchanged except for the namespace and the two
fixes below:

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

### Fixed in the move

- **The two templates hardcoded `fetch('/graphql')` three times.** A single leading slash
  is correct only on a store served from the domain root; on a subdirectory install, or
  with a store code in the URL, every page link in these same files still resolved
  correctly through `$block->getUrl()` and every data call 404'd. The endpoint is now
  resolved the same way. This is M2MAP-374 — the same defect the base package cured in its
  controllers — surviving in the one place that cure did not sweep, and the base's newly
  written Luma templates got it right by construction, so the split left it here alone.
  (M2MAP-403)
- **Nothing declared `bluefairy/module-jira-graph-ql`.** Every operation these templates
  call is declared only in that package; without it both pages render and silently do
  nothing. `composer.json` now suggests it, the README says it is not optional, and both
  templates consult the base package's new `GraphQlAvailability` view model so the absence
  becomes a notice naming the missing package. (M2MAP-401)

### Gates

- `Test/Unit/Package/StorefrontContractTest` asserts both of the above **in this package**.
  The base package sweeps this one too, but that sweep *skips* when the sibling checkout is
  absent — which is the state of any CI job or install that has only one of the two. A
  cross-package check that can skip is not this package's gate.
