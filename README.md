# ReceiveIQ

A fictional warehouse receiving application for **Hartline Specialty Foods**, used as a Strangler Fig refactoring exercise. The codebase is synthetic — designed to represent the kind of legacy Laravel application that accumulates complexity organically over several years and several contributors.

See [`legend.md`](legend.md) for the full fictional history: the company, the application's growth, the known bugs, and why they must be preserved.

## What this demonstrates

- **Characterization testing** — locking in existing behavior (including intentional bugs) before refactoring, so regressions are caught rather than silently introduced
- **Strangler Fig extraction** — `BomRecalculationService` extracted from the controller behind a feature flag, with a parity test verifying identical output from both code paths
- **Typed read models** — `ProcurementSnapshot` and `ProductionSnapshot` enforce the procurement/production inventory asymmetry structurally; the wrong field is inaccessible, not just discouraged
- **Incremental delivery** — each commit is a single logical unit; the git log is the implementation narrative. The Strangler Fig work begins at [`5a5976a`](https://github.com/Logopher/ReceiveIQ/commit/5a5976ac113d1d3f5adb20ad74b460e636c8f77f).

## Stack

Laravel 13 · PHP 8.3 · SQLite · Pest 4

## Running locally

```bash
composer install
composer run setup
php artisan test --compact
```

To test the BOM extraction with the feature flag on:

```bash
FEATURE_BOM_RECALCULATION_SERVICE=true php artisan test --compact
```
