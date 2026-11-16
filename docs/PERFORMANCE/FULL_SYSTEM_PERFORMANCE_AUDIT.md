# Zuha Full System Performance Audit

Audit date: 2026-11-14  
Status: **PARTIAL**  
Reviewed snapshot: current working tree. It contained substantial uncommitted changes before this audit; those changes were preserved.

## Executive summary

Static inspection identified and fixed two query-scaling issues in product search and redundant aggregate reads in the investor multi-currency dashboard. No schema/index changes or cache changes were made. The production Vite build succeeds.

The audit is not complete: database execution plans and endpoint timings were unavailable, and the configured isolated test database is in an inconsistent migration state. The full test run ended with 98 failures and 23 passes. The missing migration tables and conflicting existing tables prevent reliable regression conclusions. The live `.env` points at `zuha`; it was not used for tests or modified.

## Runtime and dependency versions

| Component | Observed version | Evidence |
|---|---:|---|
| PHP CLI | 8.2.12 | `php -v` |
| Laravel Framework | 12.69.2 | `composer show laravel/framework` |
| Inertia Laravel adapter | 2.0.26 | `composer show inertiajs/inertia-laravel` |
| Node | 24.20.0 | `node --version` |
| React | 19.0.0 | installed package tree |
| Inertia React | 2.0.3 | installed package tree |
| Vite | 6.1.1 | production build output |
| TypeScript | 5.7.3 | installed package tree |
| Tailwind CSS | 4.3.3 | installed package tree |
| Money arithmetic | `brick/math` 0.14.8; BCMath extension present | Composer package and `php -m` |
| Database | MySQL configured; production DB name `zuha`, test config `zuha_testing` | `.env`, `phpunit.xml` |

`npm --version` was not available through the configured PowerShell execution policy. The installed package tree supplied the relevant frontend package versions.

## Scope inspected

The source inventory covered Laravel bootstrap and configuration, providers, middleware, route files, controllers, models, policies, requests, services, support classes, migrations, seeders, jobs/events/listeners inventory, React page groups/components/hooks, TypeScript/Vite setup, and Composer/NPM manifests. Static inspection covered these business areas:

- Dashboards and analytics: main, accountant, branch, cashier, stock, investor.
- Identity and access: auth, users, branches, roles/modules/permissions, activity logs.
- Investor finance: investors, investments, wallets, transactions, profit entries, withdrawals, reports.
- Catalog and stock: products, variants, barcodes, brands, suppliers, batches, inventory, adjustments, transfers.
- Purchasing: receive flow, allocations, supplier invoices/payments, purchase reports.
- POS and sales: search, sale creation, payments, FIFO allocation, returns, receipts, sales reports.
- Accounting and currency: expenses, customer/supplier ledgers, currencies, exchange rates, purchase valuation, historical snapshots.
- Other areas: settings, notifications, backups, license, and administrative tools.

This was a source-level audit, not a production-like load test. Database schema/index inspection was limited to migrations and source references because the supplied database dump was not present and the live database was kept untouched.

## Findings and priority

| Priority | Module / file | Finding and evidence | Action / risk |
|---|---|---|---|
| P1 | POS search — `app/Http/Controllers/ProductSearchController.php` | Stock totals were queried inside both result loops. Cost grew with barcode result count plus every variant attached to up to five name matches. | Replaced with one grouped stock query for matched variant IDs. Same `products.remaining_quantity` source and trash filter. Low semantic risk. |
| P1 | Product search — `app/Http/Controllers/SearchController.php` | Same per-barcode and per-variant stock SUM pattern. | Replaced with one grouped stock query. Same stock source/filter. Low semantic risk. |
| P1 | Investor FX summary — `app/Services/InvestorMultiCurrencyDashboardService.php` | Wallet capital/profit used two aggregate reads. USD/AED cash then used separate SUM and EXISTS reads per currency: six aggregate reads total. | Wallet values now use one aggregate row; USD/AED cash uses one grouped query with conditional SUM and COUNT. No balances are cached; decimal results still pass through `Money`. Low-to-medium risk pending tests. |
| P1 | Database execution plans/indexes — migrations and query-heavy controllers | No safe, current schema dump or usable isolated test schema was available for `EXPLAIN`. | No index was added or removed without plans and live index inventory. BLOCKED. |
| P2 | Product list FX — `app/Http/Controllers/ProductController.php` | The list loads the page's catalog variants and barcodes, then fetches all purchase snapshots for those variants to compute weighted FX in PHP. Per-page scope is bounded by 15 catalog rows, but historical purchase rows per variant are not bounded. | Left unchanged because a SQL rewrite must retain exact currency-grouped decimal weighting and output. Profile with representative history before rewriting. |
| P2 | Sales reports — `app/Http/Controllers/SaleController.php` | Several independently filtered grouped reports are assembled for the same request. This can scan substantial sales history. | No consolidation without SQL plans and report reconciliation fixtures; report arithmetic is financially sensitive. |
| P2 | Returns — `app/Http/Controllers/SaleReturnController.php` | Return processing performs per-batch locking and per-entry reversal lookups. Work is transactional and likely bounded by the submitted return, but row counts are unmeasured. | Preserve locks and audit history; profile realistic multi-line returns before changing. |
| P2 | Inventory and finance dashboards | Many dashboard metrics are separate indexed-looking aggregates; actual index coverage and query cost are unverified. | No cache or aggregate-table changes. Financial balances remain uncached. |
| P3 | Frontend page delivery — `resources/js/app.tsx`, `vite.config.js` | Pages are already split through `import.meta.glob`; toaster is lazy; chart-heavy modules have separate chunks. | No speculative memoization or bundler changes. Build gives a successful current-size snapshot. |
| P3 | Dependencies and logging | No dependency upgrade/removal or broad logging change was justified by current evidence. | No change. |

## Security and financial integrity

- Search access middleware and authorization code were not changed.
- Product search stock aggregation retains its former source table and filters. Branch-aware app search remains separately scoped through `Inventory` and was not altered.
- Investor dashboard balances are still computed per request. No financial cache was introduced.
- Dashboard aggregate results remain decimal database values and are passed through existing `Money` helpers. No business formula or historical FX snapshot was changed in this audit.
- No schema, foreign key, or historical data change was made.

## Validation results

- PHP syntax checks for earlier edited files: **PASS**.
- TypeScript project check: **FAIL** due existing errors across unrelated components/pages (e.g. Recharts formatter types, missing Radix alert-dialog package, auth form types, and investor/product page types). The edited search controllers are PHP; the check did not isolate runtime behavior.
- Production Vite build: **PASS**, 2,762 modules transformed; Vite 6.1.1; build time reported by Vite: 21.50 seconds. Build output was sent to a temporary workspace directory and removed after inspection.
- Full PHP test suite against configured `zuha_testing`: **FAIL**, 98 failed / 23 passed. Failures included missing `migrations`, `users`, and other tables plus conflicts such as pre-existing `cache_locks`. This is not evidence that the query changes pass regression tests.
- Database EXPLAIN / EXPLAIN ANALYZE: **NOT MEASURED**. No usable isolated schema was confirmed.
- Endpoint response time, query duration, and query count in a live request: **NOT MEASURED**. Query changes are justified by static per-loop query evidence only.
- Browser smoke tests: **NOT RUN**.

## Remaining work

1. Repair or provision a clean isolated `zuha_testing` database using the project's approved migration process; do not point tests at `zuha`.
2. Capture query counts and response timings for POS search, investor dashboard, product list, sale creation, receiving, investor details, and large reports on representative data.
3. Run `EXPLAIN` for the hot queries against the exact current MySQL schema; check all existing indexes before recommending additions.
4. Re-run PHP feature tests, TypeScript checks, production build, and browser smoke tests after the test environment is healthy.
5. Profile product history aggregation and return reversals before making further changes.

## Overall acceptance

**PARTIAL** — two query-scaling fixes and one aggregate consolidation are implemented; full-system measurements, database plan review, and reliable regression tests remain blocked by the current test database state.
