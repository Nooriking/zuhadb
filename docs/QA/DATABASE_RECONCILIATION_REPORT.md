# Database Reconciliation Report

**Database:** `zuha_testing`  
**Source:** `QaReconciliationSeeder` + `tests/Feature/Qa/QaCompleteWorkflowSeederTest.php`  
**Date:** 2026-11-16

---

## 1. Seeder reconciliation checks

| Check | Status | Detail |
|---|---|---|
| `investors.reference` | **PASS** | King Noori blocked + Mustafa Ansari active with wallets |
| `purchases.generate_batches` | **PASS** | Each QA purchase row has a matching batch |
| `inventory.reconciles_batches` | **PASS** | Inventory qty = sum(batch remaining) per branch/variant |
| `sales.payment_balance` | **PASS** | Payment rows match `sale.paid_afn` (returned sales excluded) |
| `sales.profit_identity` | **PASS** | gross ≈ investor + store profit |
| `investor.profits.from_sales` | **PASS** | Profit entries reference sales; no orphan profit rows |
| `no_negative_batches` | **PASS** | No negative `remaining_quantity` |

**Summary: 7 PASS / 0 FAIL**

---

## 2. Automated Feature tests

| Scenario | Status |
|---|---|
| Full seed + FK / inventory / profit / idempotency | **PASS** |
| Store-owned SWY sale has zero investor profit | **PASS** |
| Ansari Al Fareed sale creates investor profit from batch ownership | **PASS** |

**3 passed / 81 assertions**

---

## 3. Scenario coverage matrix

| Requirement | Status | Notes |
|---|---|---|
| Investor creation + wallets via real workflow | **PASS** | |
| Multi-currency capital + FX | **PASS** | AFN/USD/AED via `InvestmentCapitalService` |
| Catalog + reference prices | **PASS** | |
| Store / investor / multi / mixed ownership | **PASS** | At purchase/batch level |
| AFN / USD / AED purchases | **PASS** | |
| Multi-batch FIFO sale | **PASS** | CDN qty 8 across B1+B2 |
| Partial / credit / discount / multi-branch sales | **PASS** | |
| Follow-up customer payment | **PASS** | `SaleService::addPayment` |
| Insufficient stock blocked | **PASS** | |
| Unauthorized sale | **PASS** (documented) | HTTP/policy only — not service bypass |
| Full + partial returns | **PASS** | Via `finalizeReturn` |
| Investor withdrawal + blocked denial | **PASS** | |
| Expenses / notifications / activity | **PASS** | |
| Sale cancellation with stock restore | **NOT SUPPORTED** | No cancel service in app |
| Purchase return | **NOT SUPPORTED** | No module |
| Pending unreceived purchase | **NOT SUPPORTED** | `receiveStock` immediate |
| Stock transfer completion | **NOT RUN** | Avoid inventing incomplete transfer stock moves |
| Stock adjustments | **NOT RUN** | No dedicated QA scenario |

---

## 4. Known app behaviors (not seeder bugs)

1. **Full/partial returns** reduce `sale.total_afn` / COGS / profits but do **not** rewrite historical `SalePayment` rows. Payment-balance reconciliation therefore skips returned sales.
2. After a full return, `paid_afn` can remain higher than the adjusted `total_afn` (customer credit handled via ledger / `current_due_afn`).
3. Blocked investors can still have historical batch ownership and earn profit entries from those batches; login/withdrawal is restricted.

---

## 5. Integrity verdict

| Area | Verdict |
|---|---|
| Orphan investor profits | None found for QA path |
| Duplicate QA invoices/purchases on re-seed | None (idempotent markers) |
| Negative inventory/batches | None |
| Store stock getting investor profit | None on `QA-SALE-CASH-SWY` |
| FIFO / batch ownership driving profit | Confirmed on investor sales |
| Live DB `zuha` | Untouched |

**Overall: PASS for supported workflows on `zuha_testing`.**
