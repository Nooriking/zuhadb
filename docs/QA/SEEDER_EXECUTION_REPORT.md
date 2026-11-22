# Seeder Execution Report

**Command:** `php artisan qa:seed --database=zuha_testing`  
**Environment at run:** `local`  
**Active database (override):** `zuha_testing`  
**Default `.env` database (untouched):** `zuha`  
**Final run time:** 2026-11-16  

---

## 1. Execution log (final successful run)

```
Environment: local
Database:    zuha_testing
QA database guard OK: zuha_testing
=== ZUHA QA Complete Workflow Seeder ===
→ QaCurrencyExchangeSeeder — Currencies AFN/USD/AED + historical FX board ready
→ QaFoundationSeeder — Foundation users, branches, suppliers, customers ready
→ QaInvestorSeeder — King Noori blocked; Mustafa Ansari active
→ QaProductCatalogSeeder — 22 variants ready
→ QaPurchaseInventorySeeder — Purchases received via PurchaseService: 19 new; batches total=19
→ QaSaleWorkflowSeeder — Insufficient-stock blocked; follow-up payment via SaleService::addPayment; Sales: 13
→ QaReturnWithdrawalSeeder — Returns + withdrawals ready
→ QaSupportingModulesSeeder — Expenses, notifications, activity ready
→ QaReconciliationSeeder — Reconciliation: 7 PASS / 0 FAIL
=== QA Complete Workflow finished ===
```

Exit code: **0**

---

## 2. Record counts (after final seed)

| Entity | Count |
|---|---:|
| Users (incl. investors) | 8 |
| Investors | 3 |
| Brands | 17 |
| Catalog products | 22 |
| QA purchases (`QA-PO-%`) | 19 |
| Product batches | 19 |
| Inventory rows | 16 |
| QA sales (`QA-SALE-%`) | 13 |
| Sale payments | ~13+ (includes follow-up) |
| QA returns | 2 |
| Investor profit entries | 11+ |
| Investor withdrawals | 2–3 |
| QA expenses | 2 |
| Currencies | 3 (AFN, USD, AED) |

---

## 3. Idempotency

Second run of `qa:seed` on the same DB:

- Purchases created: **0 new**
- Sales created: **0 new**
- Markers (`notes` / `invoice_number` / `reason`) prevent duplicates

---

## 4. Automated tests (executed)

```
php artisan test --env=testing tests/Feature/Qa/QaCompleteWorkflowSeederTest.php
```

| Test | Result |
|---|---|
| seeds complete QA workflow idempotently with relational integrity | **PASS** |
| blocks store-owned stock from inventing investor profit on QA cash sale | **PASS** |
| allocates investor profit from historical batch ownership on Ansari Al Fareed sale | **PASS** |

**3 passed (81 assertions)** — Duration ~39s  

Note: Feature tests use `RefreshDatabase` against `zuha_testing` only (`TestCase` refuses any other DB). After tests, `qa:seed` was re-run so manual QA data remains available.

---

## 5. Destructive commands

| Command | Status |
|---|---|
| `migrate:fresh` on `zuha` | **NOT RUN** |
| `db:wipe` on `zuha` | **NOT RUN** |
| `TRUNCATE` on production tables | **NOT RUN** |
| Seed against `zuha` | **REFUSED** by allow-list |

---

## 6. Login accounts for manual QA

Point the app at `zuha_testing` (e.g. temporary `.env` `DB_DATABASE=zuha_testing` or a dedicated QA env). Passwords used by seeders:

| Username | Role | Password |
|---|---|---|
| `qa_admin` | admin | `QaAdmin@123` |
| `superadmin` | super_admin | `Super@123` |
| `qa_cashier` | cashier | `QaCashier@123` |
| `qa_stock` | stock_keeper | `QaStock@123` |
| `AnsariUAE` | investor (active) | `Investor@123` |
| `new` | investor (**blocked**) | `Investor@123` |

Do not use these against the live `zuha` database.
