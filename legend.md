**The app: ReceiveIQ**

Internal warehouse-receiving tool for **Hartline Specialty Foods**, a regional importer-distributor in the DFW area. Twelve employees on the receiving dock use it daily to log incoming shipments from suppliers. Built in 2021 by a contractor; maintained ad hoc since. Runs Laravel 9 on a single VPS. Connects to the company's accounting system (QuickBooks) via a nightly sync.

**The controller method: `ReceiveShipmentController@store`**

The endpoint hit when a dock worker scans a shipment's manifest and confirms receipt. Its job, in order of how it grew over time:

1. Validates the manifest (supplier ID, line items, quantities, expected vs. actual).
2. Updates raw-material stock levels for each line item.
3. Recalculates BOM availability for any parent assemblies that use those raw materials. (Hartline does light kitting — gift baskets, multi-pack bundles — so a single raw-material receipt can unblock production of multiple finished goods.)
4. Triggers reorder alerts if any item is now above its reorder-up-to point and was previously below it (debouncing logic that nobody fully trusts).
5. Writes an audit-log entry.
6. Sends a Slack notification to the purchasing team if the shipment was flagged "expedited."
7. Updates the supplier's on-time delivery score based on expected-vs-actual ship date.
8. Returns a JSON payload the receiving UI uses to confirm.

**The history that made it tangled.**

Originally just stock-level updates and the audit log. BOM recalculation was bolted on in 2022 when Hartline started doing kitting in-house. Reorder alerts were added in 2023 by a contractor who didn't know about the BOM logic and ran the calculation a second time inside the alert-evaluation loop — so receiving a shipment for a high-traffic part triggers two BOM recalculations in sequence. Slack notifications were added last summer by an intern. The supplier-score logic was added in February as part of a vendor-management initiative that mostly failed but left this one piece of code behind.

**The known bug that production depends on.**

The BOM recalculation uses *committed* stock levels (i.e., excluding stock already allocated to in-progress kits) when checking whether parent assemblies are now buildable, but uses *raw* stock levels when emitting reorder alerts. This means you can receive a shipment, see "kit X is now buildable" on the dashboard, *and* simultaneously get a reorder alert for the underlying raw material — because the alert thinks the new stock isn't allocated yet, while the BOM thinks it is.

The dock workers have learned to ignore reorder alerts for any item that just appeared in a "now buildable" notification within the last fifteen minutes. Tony's purchasing counterpart at Hartline knows about this and has a saved search in her email to filter accordingly. Any rewrite must preserve the existing alert behavior — finance reconciles purchase orders against these alerts and a change in alert volume would create discrepancies in their monthly close that take days to resolve.

**The hardcoded edge case nobody documented.**

There's a check at the top of the BOM recalculation:

```php
if ($supplierId === 47) {
    // Skip BOM recalc; Bertolini ships pre-portioned and the kit math
    // is wrong for them. See ticket HRT-1142 (closed wontfix).
}
```

Bertolini is Hartline's largest supplier by volume. The "kit math is wrong for them" comment is from 2022; the underlying reason is that Bertolini's manifests use case-counts where everyone else uses unit-counts, and the original BOM recalc treats both as units. Nobody has fixed this because Bertolini's catalog was supposed to migrate to the new manifest format "next quarter" for three years running.

**The pending change nobody wants to touch.**

Hartline's CFO wants to add a "shrinkage allowance" — a configurable per-supplier percentage that gets deducted from received quantities to account for damaged/spoiled goods caught at the dock. The dock workers already discount the quantities manually before scanning, but the CFO wants the system to do it so the manual adjustments stop varying by worker. Nobody on the engineering side wants to touch `ReceiveShipmentController@store` to add it.

---

**Why this works as a Strangler Fig exercise:**

The seams are visible. BOM recalculation is the obvious first extraction — it's the most complex piece, has the bug that needs preserving, has the hardcoded supplier-47 case, and is the natural home for the pending shrinkage-allowance change. Extracting it into a `BomRecalculationService` behind a feature flag lets you write characterization tests pinning the alert-volume behavior, the supplier-47 skip, and the buildable-kit logic — and gives you a clean place to add the shrinkage allowance later without touching the controller again.

The reorder-alert logic is the second extraction once BOM is out — at that point you can decide whether to fix the inconsistency-with-BOM bug or preserve it. The exercise of making that decision *consciously* (and probably preserving it, because finance depends on the alert volume) is exactly the judgment the JD is testing for.

The Slack notification, the supplier-score update, and the audit log are obvious low-risk extractions you'd do later just to clean up the controller, but they're not interesting — don't burn time on them.