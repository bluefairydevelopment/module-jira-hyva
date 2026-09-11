# Changelog — BlueFairy_JiraHyva

## 1.1.0 — 2026-09-11

First release, and it opens at 1.1.0 rather than 1.0.0 deliberately: the three
Jira packages ship as a set and are versioned together, so a merchant reporting a
problem names one number rather than three. Jennifer's ruling, 2026-09-10.

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

- **`view/frontend/tailwind/module.css` declared no `@source` glob**, so Hyvä's Tailwind
  build imported the file and scanned none of this package's templates. The file was
  1,466 bytes of comment — including a comment stating that this file is what brings the
  classes into the compiled stylesheet. `npx hyva-sources` emitted the `@import` exactly
  as described; the `@import` imported a comment.

  Measured on `hyvatest` against the **served** stylesheet, 2026-09-11: **39 of 110**
  distinct class tokens across the two templates had no rule. After adding one line:
  **0 of 110**, with the same sweep still returning 39 against the pre-cure stylesheet.
  In the browser the required asterisks rendered black instead of red, the Submit Ticket
  button had no padding, and the field spacing was gone — with no console error and no
  404, on pages that returned 200 with correct markup and passed every other gate.

  M2MAP-359 is the ticket that recorded this defect, and its measurement was sitting in
  this file's own docblock as though it were history. Estate denominator at the time: 7
  Hyvä companions ship a `view/frontend/tailwind/module.css`; 6 declared an `@source`;
  this one declared none.

### Gates

- `Test/Unit/Package/TailwindScansThisPackagesTemplatesTest` pins the above. No other gate
  in this package can see it: phpcs and phpstan do not read CSS, phpunit was green across
  it, and the storefront returns 200 with the right markup either way. It strips comments
  before looking, because the broken state's only mention of `@source` was inside one, and
  it carries a control for its own stripper — the obvious block-comment regex eats
  `../templates/**/*.phtml`, rewrites it to `../templates*.phtml`, and then reports the
  package's own templates as uncovered. That happened on the first run.
- `Test/Unit/Package/StorefrontContractTest` asserts both of the above **in this package**.
  The base package sweeps this one too, but that sweep *skips* when the sibling checkout is
  absent — which is the state of any CI job or install that has only one of the two. A
  cross-package check that can skip is not this package's gate.
