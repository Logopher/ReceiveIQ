# Environment & Constraints

## Runtime

This project uses [Vigil](https://github.com/Logopher/Vigil) as its Claude Code baseline. Run sessions with the `vigil-dev` policy (`vigil-dev` in the terminal).

- **PHP, Composer, and Artisan** are available without restriction.
- **Node, npx, and npm** are denied by default. Frontend build steps (`npm run dev`, `vite build`) require the `vigil-dev` policy to be active and may prompt for confirmation.

## Database

The Boost MCP Database Query tool may connect as a read-only user. Treat all MCP database access as read-only regardless. Do not attempt INSERT, UPDATE, DELETE, or DDL via the MCP tool — use Artisan migrations for schema changes and Eloquent for application writes.

If a destructive database operation is genuinely required, state it explicitly and wait for confirmation before proceeding.

## Commit Scopes

| Scope | Covers |
|-------|--------|
| `receiving` | Shipment receiving flow — `ReceiveShipmentController`, `ReceiveShipmentRequest`, `ShipmentLineItem` |
| `bom` | BOM recalculation — `BomRecalculationService`, `BomComponent`, `Assembly` |
| `inventory` | Raw material stock — `RawMaterial`, stock/committed quantity logic |
| `suppliers` | Supplier model, on-time scoring, shrinkage allowance |
| `alerts` | Reorder alert logic and `ReorderAlertTriggered` event |
| `notifications` | Expedited shipment notification and future outbound notifications |
| `audit` | `AuditLog` model and all audit-write paths |
| `migrations` | Database schema changes |
| `config` | Feature flags (`config/features.php`) and environment config |

## Strangler Fig

This is a Strangler Fig engagement on a legacy Laravel codebase. Default to preserving existing behavior unless explicitly told otherwise.

- Do not refactor, rename, or restructure legacy code unless instructed.
- New code goes in the designated strangler layer.
- Legacy code is touched only to introduce seams: interfaces, adapters, or feature flags.
- When in doubt between preserving legacy behavior and applying a Laravel convention, preserve legacy behavior and surface the conflict.