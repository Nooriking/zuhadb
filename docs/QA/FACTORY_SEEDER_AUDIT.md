# Factory & Seeder Audit — ZUHA PERFUME POS

**Date:** 2026-11-16  
**Scope:** Full application audit before QA factory/seeder execution  
**Production DB (do not seed):** `zuha` (from `.env`)  
**QA DB (allow-listed):** `zuha_testing` (from `.env.testing` / `phpunit.xml`)

---

## 1. Application structure (actual)

| Area | Finding |
|---|---|
| Stack | Laravel + Inertia, MySQL |
| Investor identity | `App\Models\Investor` extends `User` (`users` table, `role=investor`) |
| Ownership | Purchase / batch level (`investor_id` on purchase rows & `product_batches`), plus `investment_product_allocations` |
| Inventory source | Only via `PurchaseService::receiveStock` → batch + `Inventory::increase` |
| Sales / FIFO | `SaleService::createSale` — FIFO batch consumption, COGS, investor/store profit |
| Capital | `InvestmentCapitalService::deposit` (never fabricate wallet balances) |
| Follow-up payments | `SaleService::addPayment` |
| Returns | `SaleReturnController::finalizeReturn` (private; QA seeder invokes via reflection — same production path) |
| FX | `CurrencyService::postRate` + transaction FX board snapshots (AFN/USD/AED) |

### Roles (User model constants)

`super_admin`, `admin`, `branch_manager`, `cashier`, `stock_keeper`, `investor`  
No Spatie role package — role column on `users`.

### Modules that do **not** exist (do not invent)

| Requested scenario | Status |
|---|---|
| Purchase return workflow | **NOT SUPPORTED** — no PurchaseReturn model/service |
| Pending (unreceived) purchase | **NOT SUPPORTED** — `receiveStock` is immediate |
| Sale cancellation with stock restore | **NOT SUPPORTED** — no cancel service; `destroy()` soft-trashes only |
| Categories table | **NOT PRESENT** — brands only |
| Dedicated cash-management module | **NOT PRESENT** as separate tables |

---

## 2. Existing factories (40)

Located under `database/factories/`. Key ones used by QA:

- `InvestorFactory` — states `kingNoori()`, `mustafaAnsari()`, wallet via `getOrCreateWallet()`
- `UserFactory`, `BranchFactory`, `BrandFactory`, `ProductFactory`, `ProductVariantFactory`
- `SaleFactory`, `CustomerFactory`, `SupplierFactory`, currency/FX factories
- Financial: `InvestmentFactory`, `InvestorWalletFactory`, `InvestorProfitEntryFactory`, etc.

Factories generate valid base rows; **business workflows belong in seeders + services**.

---

## 3. Existing seeders

### Production-safe handoff (`DatabaseSeeder`)

Calls only:

1. `SuperAdminSeeder`
2. `CompanySettingSeeder`

Demo/legacy seeders (`FullScenarioSeeder`, `DemoDataSeeder`, `FactorySampleSeeder`, …) are **not** wired into `DatabaseSeeder`.

### QA workflow (allow-listed)

Entry: `php artisan qa:seed --database=zuha_testing`  
Class: `Database\Seeders\QaCompleteWorkflowSeeder`

Order:

1. `QaCurrencyExchangeSeeder`
2. `QaFoundationSeeder`
3. `QaInvestorSeeder`
4. `QaProductCatalogSeeder`
5. `QaPurchaseInventorySeeder`
6. `QaSaleWorkflowSeeder`
7. `QaReturnWithdrawalSeeder`
8. `QaSupportingModulesSeeder`
9. `QaReconciliationSeeder`

Guard: `GuardsQaDatabase` — refuses any DB not in `QA_SEED_ALLOWED_DATABASES` (default `zuha_testing`).

---

## 4. Key services seeders must call

| Workflow | Entry point |
|---|---|
| FX board | `CurrencyService::postRate` |
| Investor capital | `InvestmentCapitalService::deposit` |
| Receive stock / ownership | `PurchaseService::receiveStock` |
| Sale + FIFO + profits | `SaleService::createSale` |
| Follow-up payment | `SaleService::addPayment` |
| Return finalize | `SaleReturnController::finalizeReturn` |
| Activity | `ActivityLogger::log` |

---

## 5. Files created / modified in this delivery

### Modified

- `database/seeders/Qa/QaSaleWorkflowSeeder.php` — follow-up payment via `SaleService::addPayment`; repair path; documented unsupported cancel
- `database/seeders/Qa/QaReconciliationSeeder.php` — payment check excludes returned sales (totals adjusted by return flow)

### Created

- `tests/Feature/Qa/QaCompleteWorkflowSeederTest.php`
- `docs/QA/FACTORY_SEEDER_AUDIT.md` (this file)
- `docs/QA/INVESTOR_PRODUCT_SALE_SEEDING_REPORT.md`
- `docs/QA/SEEDER_EXECUTION_REPORT.md`
- `docs/QA/DATABASE_RECONCILIATION_REPORT.md`
- `docs/QA/MANUAL_TEST_DATA_GUIDE.md`

### Already present (audited, reused — not duplicated)

- `app/Console/Commands/QaSeedCommand.php`
- `database/seeders/QaCompleteWorkflowSeeder.php`
- All `database/seeders/Qa/*` seeders and concerns
- `database/factories/InvestorFactory.php` (reference investor states)

---

## 6. Safety verification (executed)

| Check | Result |
|---|---|
| `.env` `DB_DATABASE` | `zuha` — **production/live — never targeted** |
| `.env.testing` / phpunit | `zuha_testing` |
| `qa:seed --database=zuha_testing` | Guard OK; seeded |
| `migrate:fresh` / `db:wipe` on `zuha` | **NOT RUN** |
| Credentials in reports | **Not exposed** |
