# ZUHA POS — Local Repair & Deployment Readiness

**Date:** 2026-11-23  
**Environment worked on:** LOCAL only (`127.0.0.1` / `zuha`)  
**Production:** Not contacted, not modified, not deployed.

Companion audit: `docs/audits/LOCAL_PRE_DEPLOYMENT_AUDIT.md`

---

## A. Root causes

1. **Production crash (`Unknown column 'investment_id'`)**  
   Application code already uses `product_batches.investment_id`, but production DB still has the pre-simplify schema (`investor_id` only). Migration `2026_11_22_100000_simplify_product_batch_investment_schema` had not been applied there.

2. **Unsafe orphan backfill in that migration**  
   Batches with `investor_id` but no allocation row were linked to the investor’s *latest* investment, which mis-attributes capital/profit when an investor has multiple investments.

3. **Fragile FK drops**  
   Migration dropped FKs only by fixed Laravel default names; a differently named production FK could abort mid-migration before `investor_id` was removed.

4. **`customer_balances` drop conflict**  
   Simplify migration dropped `customer_balances`, then `2026_11_22_151000` recreated it empty — risk of multi-currency due data loss.

5. **Stock transfers lost ownership**  
   Destination batches were created without `investment_id` / cost snapshot fields → investor stock became store-owned after transfer.

6. **Post-migrate column selects**  
   `InvestorTransactionController` eager-loaded dropped `investment_number` / `capital_amount_afn`.

7. **Dashboard Vite manifest gap**  
   `resources/js/pages/dashboard.tsx` collided with `components/dashboard/*` chunk naming on Windows/Vite and was missing from `public/build/manifest.json`, causing HTTP 500 (`ViteException`) when rendering `/dashboard`.

8. **Factories/tests/seeders still targeted removed columns**  
   Unguarded factories wrote `purchase_price_afn`, `source_type`, `product_batches.investor_id`, etc.

---

## B. Changes made

| File | Reason |
|------|--------|
| `database/migrations/2026_11_22_100000_simplify_product_batch_investment_schema.php` | Dedicated backfill investment per orphan batch; column-based FK discovery; do not drop `customer_balances`; idempotent FK add guards |
| `app/Http/Controllers/StockTransferController.php` | Preserve `investment_id` + cost/profit/purchase snapshot on transfer |
| `app/Http/Controllers/InvestorTransactionController.php` | Eager-load existing investment columns |
| `app/Http/Controllers/InvestorWithdrawalController.php` | Same |
| `app/Http/Controllers/InvestorDashboardController.php` | Expose `fxBoard` alias |
| `app/Http/Controllers/DashboardController.php` | Render `dashboards/overview` |
| `app/Http/Controllers/ProductController.php` | Neutralize dead helpers that queried removed batch columns |
| `app/Models/Investment.php` | Accessors/appends for `investment_number`, `capital_amount_*` UI compatibility |
| `app/Models/PurchaseItem.php` | Soften obsolete `batches()` relation |
| `database/factories/ProductBatchFactory.php` | `storeOwned()` state |
| `database/factories/ProductVariantFactory.php` | Strip legacy columns under factory unguard |
| `database/factories/InvestmentFactory.php` | Strip/map legacy investment columns |
| `database/seeders/InvestorDashboardSeeder.php` | Align with slim schema + `InvestorTransaction` |
| `resources/js/pages/dashboards/overview.tsx` | Moved main dashboard page (was `pages/dashboard.tsx`) |
| `vite.config.js` | Explicit Vite input for overview page |
| Tests listed in git diff | Assert `investment_id` architecture; fix obsolete expectations |
| `docs/audits/*` | Audit + readiness reports |

`.env` was **not** modified. `APP_KEY` unchanged.

---

## C. Database changes

### Intended final schema (ownership)

- `product_batches.investment_id` nullable FK → `investments.id` (`ON DELETE SET NULL`)
- `investment_id IS NULL` = store-owned  
- `investment_id IS NOT NULL` = investor-funded  
- `investments.investor_id` → `users.id`  
- Legacy `product_batches.investor_id` removed **after** backfill

### Local status

- Migration already **Ran** on local `zuha` and `zuha_testing`
- Integrity: 0 orphan `investment_id` FKs; valuation loads; `/dashboard` HTTP 200

### Safe production application sequence (DO NOT RUN HERE)

1. Full DB backup (mysqldump / snapshot).  
2. Deploy code **and** run migrations in the **same** maintenance window (never leave new code live against old schema).  
3. `php artisan migrate --force` (applies `2026_11_22_100000` and dependents if pending).  
4. `npm run build` (or deploy prebuilt `public/build`).  
5. Smoke: login → Dashboard → Inventory valuation → Investor dashboard → one sale.

Edited migration file will apply on production only if that migration has **not** already run there (status shows Pending). Local already Ran → local will not re-execute it.

---

## D. Data preservation

- Allocation rows (`investment_product_allocations`) map batch → investment first (preferred).  
- Remaining investor-owned batches without allocation get a **new dedicated** backfill investment (notes identify source batch id) — no reuse of “latest investment”.  
- Store-owned batches stay `investment_id = NULL`.  
- `customer_balances` no longer dropped by simplify migration.  
- Rollback of simplify migration is **not** automatic (`down()` throws).

---

## E. Test results

### Passed (core ownership / dashboard)

- `DashboardTest` — all  
- `DashboardThreeCurrencyTest` — all  
- `InventoryValuationConsistencyTest` — all  
- `InvestorDashboardTest` — all  
- `InvestorShowAuthorizationTest` — all  
- `FactorySchemaSmokeTest` — all  
- `ProductBatchNoSnapshotColumnsTest` — all  

### Failed / outdated (remaining)

- `InvestorMultiCurrencyDashboardTest` — still queries removed `products.investor_id` / `product_batches.investor_id`, uses removed `InvestorProfitEntry`, and expects old `currencySections` Inertia props. **Not a runtime production blocker for the Dashboard SQL crash**; suite needs a follow-up rewrite.  
- Some `PosCoreScenariosTest` receive/sale cases still use outdated `/products/receive` payloads (pre-existing API drift).

### Other verification

- Frontend `npm run build` — success  
- Local `/dashboard` HTTP — **200**  
- `ProductBatch::valuationSummary()` — OK on local data  

---

## F. Remaining risks

1. Production must run the simplify migration **before or atomically with** this code.  
2. Migration is irreversible without restore-from-backup.  
3. Orphan backfill creates one investment per unmapped batch (may increase investment count).  
4. `InvestorMultiCurrencyDashboardTest` and parts of PosCore/BusinessLogic suites are schema-stale.  
5. `PurchaseItem` model remains legacy/orphaned (no `purchase_items` table).  
6. Built assets (`public/build`) must ship with the dashboard overview page entry.

---

## G. Production deployment plan (not executed)

1. Announce maintenance window; put app in maintenance mode.  
2. Take a verified database backup.  
3. Deploy this codebase (including `public/build` and migration edits).  
4. Run `php artisan migrate --force`.  
5. Clear caches: `php artisan optimize:clear` then `config:cache` / `route:cache` / `view:cache` as usual.  
6. Smoke checks (section C).  
7. Exit maintenance mode.  
8. Monitor logs for `investment_id` / Vite / investment transaction errors for 24h.

---

## H. Rollback plan

| Layer | Action | Limitation |
|-------|--------|------------|
| Code | Redeploy previous release | Old code expects `investor_id` on batches if DB already migrated |
| DB | Restore backup taken in step G.2 | Only reliable DB rollback; migration `down()` is intentionally blocked |
| Assets | Redeploy previous `public/build` | Required if dashboard page path changed |

**Do not** roll back code alone after the simplify migration has run.

---

## I. Deployment readiness verdict

**READY WITH CONDITIONS**

Conditions:

1. Production DB backup completed and verified.  
2. Migrate + code deploy in one window (no split deploy).  
3. Post-deploy smoke of Dashboard, inventory valuation, investor pages, and a sample sale.  
4. Accept that `InvestorMultiCurrencyDashboardTest` / some PosCore receive tests remain outdated and should be cleaned in a follow-up (not blocking the `investment_id` production crash fix).
