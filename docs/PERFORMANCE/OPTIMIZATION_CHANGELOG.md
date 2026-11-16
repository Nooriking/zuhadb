# Performance Optimization Changelog

Audit date: 2026-11-14

## Implemented

### Grouped stock lookup in product search

Files:

- `app/Http/Controllers/ProductSearchController.php`
- `app/Http/Controllers/SearchController.php`

Both endpoints now collect matched variant IDs and run one grouped `SUM(remaining_quantity)` query rather than issuing a stock query per result and per variant. Existing matching limits, stock source, trash filter, output fields, and authorization behavior remain unchanged.

Expected query shape: barcode results `B` plus variants under name results `V` previously caused up to `B + V` stock queries. The new path issues zero stock queries for no matched variants or one grouped query otherwise. `V` is not capped by the five product result limit.

### Investor FX summary aggregate consolidation

File:

- `app/Services/InvestorMultiCurrencyDashboardService.php`

Wallet capital and profit now come from one aggregate query. USD and AED cash available amounts and the existence of a cash track now come from one grouped query using a conditional SUM and COUNT. Together, those balance checks use two aggregate reads instead of six. Existing `Money` rounding and original-currency logic remain in place.

## Deliberately unchanged

- No index migrations: the current schema/index inventory and execution plans were unavailable.
- No financial cache: invalidation and branch/investor scope have not been demonstrated safe.
- No report/FIFO/return rewrites: query plans and regression fixtures are unavailable, and those flows are financially sensitive.
- No package upgrades or dependency removals.

## Verification

- Production Vite build succeeded (2,762 modules; 21.50 seconds as reported by Vite). Output was directed to `.audit-build` and then removed.
- Full PHP suite: 98 failures / 23 passes, blocked by missing/conflicting tables in `zuha_testing`.
- TypeScript: fails on existing project errors. No edited React file was implicated by the reported errors.
- No SQL timing or runtime query-count instrumentation was available; static query-shape counts above are not response-time claims.
