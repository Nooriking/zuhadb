# Before / After Performance Metrics

Audit date: 2026-11-14

The project did not have a healthy isolated database for repeatable endpoint benchmarks. `.env` names the live database `zuha`; `phpunit.xml` correctly names `zuha_testing`, but the test run found missing migration tables and conflicting tables there. No endpoint timing or SQL execution plan is claimed below.

## Static query-shape comparison

| Workload | Before | After | Status |
|---|---:|---:|---|
| ProductSearchController stock reads | Up to `B + V`, where `B` is barcode matches and `V` is variants belonging to up to 5 name matches | 0 when no variant matched; otherwise 1 grouped stock query | Implemented; runtime query count NOT MEASURED |
| SearchController stock reads | Up to `B + V`, same result shape | 0 when no variant matched; otherwise 1 grouped stock query | Implemented; runtime query count NOT MEASURED |
| Investor FX dashboard balance aggregates | 6 aggregate reads: 2 wallet SUMs and 2 reads (SUM + EXISTS) for each of USD and AED | 2 aggregate reads: one wallet aggregate and one grouped currency aggregate | Implemented; runtime query count NOT MEASURED |

`B + V` is a source-level query count estimate from the loops, not a measured database trace. Queries for matching products, barcodes, and eager-loaded relationships are additional and unchanged.

## Build and test measurements

| Check | Result |
|---|---|
| PHP version | 8.2.12 |
| Production Vite build | PASS — 2,762 modules transformed; 21.50 seconds |
| Largest emitted JS asset | 381,896 bytes (`app` entry chunk) |
| Next largest emitted JS asset | 311,522 bytes (`CategoricalChart` chunk) |
| CSS asset | 140,463 bytes |
| PHP test suite | FAIL — 98 failed / 23 passed; migration/schema state in `zuha_testing` is inconsistent |
| TypeScript check | FAIL — existing type/dependency errors across multiple unrelated files |
| Endpoint wall-clock response times | NOT MEASURED |
| Database query durations / EXPLAIN plans | NOT MEASURED |
| Browser rendering / interaction timings | NOT MEASURED |

Asset sizes are raw emitted bytes from the audit build. Compressed transfer size was not reported because Vite's `reportCompressedSize` is disabled in the project configuration.

## Measurement plan

After a clean isolated schema and representative data set are available, repeat these measurements with identical inputs and warm/cold cache conditions:

1. POS barcode and name search: database query count, total SQL duration, endpoint duration; include products with many variants.
2. Investor multi-currency dashboard: query count, total SQL duration, endpoint duration; test both all-investor and single-investor views.
3. Product list and large sales/profit reports: row counts, query plans, endpoint duration, memory use.
4. Sale/return workflows: request duration and SQL duration while confirming FIFO, ledger, and reversal outputs match exactly.
5. Browser route transitions: transferred JS/CSS bytes and interaction-to-ready timing for dashboards and POS.
