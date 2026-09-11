# Shipped images — reviewed record

Every image in `docs/images/` is pinned here by SHA-256, matching the record the base package
keeps. A test cannot read a picture; it can refuse to ship bytes nobody has attested to.

**These four arrived from `bluefairy/module-jira`.** They were captured on Hyvä while the base
package still shipped Alpine and Tailwind templates. Since the split the base ships **Luma**
templates and its captures are Luma, so these belong with the templates they actually depict —
which is this package. They were not re-shot and not discarded; they were filed correctly.

| File | Bytes | SHA-256 | Reviewed note |
|------|-------|---------|---------------|
| `frontend-account-nav.png` | 216944 | `1d9e83cd2614493d…` | HYVA. Customer account sidebar. PERSONAL DATA BLURRED DESTRUCTIVELY: the contact card, both address blocks and the recent-orders rows carried a name, an email address, a telephone number and a full billing and shipping address. Those regions are downsampled then smoothed, so the text is gone rather than smeared, while the sidebar this image exists to document is intact (BFDPROD-18). |
| `frontend-my-tickets.png` | 130314 | `e7ca4647d5562eb0…` | HYVA. Ticket history with synthetic summaries and an order reference. No personal data. |
| `frontend-submit-ticket-form-filled.png` | 159872 | `cb7f5fe65bf49c37…` | HYVA. Filled form, synthetic support text. No personal data. |
| `frontend-submit-ticket-form.png` | 144248 | `33bcccdb20cabd66…` | HYVA. Empty form. No data of any kind. |

## Not carried over

- `customer-ticket-submit.png` was **removed from the estate**, not moved here. It depicted a
  customer being asked to hand over hosting access ("you need to ftp in with this account"),
  which teaches buyers that credentials belong in support tickets, and it duplicated the
  filled-form capture. Moving it would have relocated a content defect rather than fixed it.
