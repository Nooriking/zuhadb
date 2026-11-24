# Production Data Integrity Checks — Zuha POS

**Date:** 2026-11-23  
**Mode:** READ-ONLY SQL for operators  
**Usage:** Run **before** migrate (baseline) and **after** migrate (compare). Do not UPDATE/DELETE.

Queries are dual-aware where schema may differ (pre vs post simplify). Skip statements that error with “Unknown column/table” and record which branch applies.

---

## 0. Capture baseline metadata

```sql
SELECT NOW() AS checked_at, DATABASE() AS db_name;

SELECT migration, batch
FROM migrations
ORDER BY id;

SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
```

---

## 1. Inventory quantities

```sql
-- Batch remaining stock
SELECT
  COUNT(*) AS batch_rows,
  COALESCE(SUM(remaining_quantity), 0) AS sum_remaining,
  COALESCE(SUM(quantity), 0) AS sum_original_qty
FROM product_batches
WHERE trash = 0
  AND deleted_at IS NULL;

-- Inventory table totals
SELECT
  COUNT(*) AS inventory_rows,
  COALESCE(SUM(quantity), 0) AS sum_inventory_qty
FROM inventories
WHERE trash = 0;

-- Branch × variant mismatch probe (adjust column names if local differs)
SELECT i.branch_id, i.variant_id,
       i.quantity AS inventory_qty,
       COALESCE(b.batch_remaining, 0) AS batch_remaining
FROM inventories i
LEFT JOIN (
  SELECT branch_id, variant_id, SUM(remaining_quantity) AS batch_remaining
  FROM product_batches
  WHERE trash = 0 AND deleted_at IS NULL
  GROUP BY branch_id, variant_id
) b ON b.branch_id = i.branch_id AND b.variant_id = i.variant_id
WHERE i.trash = 0
  AND i.quantity <> COALESCE(b.batch_remaining, 0)
LIMIT 100;
```

**Expect:** Pre/post sums of `remaining_quantity` and inventory quantities **unchanged** by migrations (ownership remap must not alter qty).

---

## 2. Batch totals / costs

```sql
SELECT
  COUNT(*) AS batches,
  COALESCE(SUM(unit_cost_afn * remaining_quantity), 0) AS approx_stock_value_afn,
  COALESCE(SUM(total_cost_afn), 0) AS sum_total_cost_afn
FROM product_batches
WHERE trash = 0 AND deleted_at IS NULL;

-- Store vs investor ownership (POST-simplify)
SELECT
  SUM(CASE WHEN investment_id IS NULL THEN 1 ELSE 0 END) AS store_owned_batches,
  SUM(CASE WHEN investment_id IS NOT NULL THEN 1 ELSE 0 END) AS investor_batches
FROM product_batches
WHERE trash = 0 AND deleted_at IS NULL;

-- PRE-simplify ownership
SELECT
  SUM(CASE WHEN investor_id IS NULL THEN 1 ELSE 0 END) AS store_owned_batches,
  SUM(CASE WHEN investor_id IS NOT NULL THEN 1 ELSE 0 END) AS investor_batches
FROM product_batches
WHERE trash = 0 AND deleted_at IS NULL;
```

---

## 3. Sales totals

```sql
SELECT
  COUNT(*) AS sales_count,
  COALESCE(SUM(total_afn), 0) AS sales_total_afn
FROM sales
WHERE deleted_at IS NULL;

SELECT
  COUNT(*) AS sale_item_rows,
  COALESCE(SUM(quantity), 0) AS items_qty,
  COALESCE(SUM(line_total_afn), 0) AS items_line_total_afn,
  COALESCE(SUM(investor_profit_afn), 0) AS items_investor_profit_afn,
  COALESCE(SUM(store_profit_afn), 0) AS items_store_profit_afn,
  COALESCE(SUM(COALESCE(gross_profit_afn, 0)), 0) AS items_gross_profit_afn
FROM sale_items;

-- After 11_14_140000, expect approximately:
-- store_profit_afn ≈ gross_profit_afn - investor_profit_afn
SELECT COUNT(*) AS inconsistent_profit_rows
FROM sale_items
WHERE ABS(
  ROUND(COALESCE(store_profit_afn,0) - (COALESCE(gross_profit_afn,0) - COALESCE(investor_profit_afn,0)), 2)
) > 0.05;
```

**Expect:** `sales_total_afn` and `items_line_total_afn` unchanged. Profit **identity** may change (gross vs store residual) after Group B — compare with documented rewrite, not raw store_sum equality.

---

## 4. Sale payments

```sql
SELECT
  COUNT(*) AS payments,
  COALESCE(SUM(amount_afn), 0) AS payments_total_afn
FROM sale_payments
WHERE deleted_at IS NULL;
```

---

## 5. Investor capital

```sql
-- PRE-simplify capital columns
SELECT
  COUNT(*) AS investments,
  status,
  COALESCE(SUM(capital_amount_afn), 0) AS capital_afn
FROM investments
WHERE trash = 0 AND deleted_at IS NULL
GROUP BY status;

-- POST-simplify
SELECT
  COUNT(*) AS investments,
  status,
  COALESCE(SUM(amount_afn), 0) AS amount_afn
FROM investments
WHERE trash = 0 AND deleted_at IS NULL
GROUP BY status;

-- Status distribution (catch incorrect mass-close)
SELECT status, COUNT(*) AS cnt
FROM investments
WHERE trash = 0 AND deleted_at IS NULL
GROUP BY status;
```

**Expect after fixed mapping:** investments that were `partially_used`/`fully_used` should land as **`active`** (unless product policy explicitly closes them). Mass `closed` spike vs baseline is a **STOP** signal.

Investment **row count** may **increase** by orphan-batch backfill count — record pre-count of investor-owned batches without allocation.

---

## 6. Wallet balances

```sql
-- PRE
SELECT
  COUNT(*) AS wallet_rows,
  COALESCE(SUM(capital_balance_afn), 0) AS capital_afn,
  COALESCE(SUM(profit_balance_afn), 0) AS profit_afn
FROM investor_wallets;

-- POST
SELECT
  COUNT(*) AS wallet_balance_rows,
  COALESCE(SUM(capital_balance_afn), 0) AS capital_afn,
  COALESCE(SUM(profit_balance_afn), 0) AS profit_afn
FROM investor_wallet_balances;
```

**Expect:** Post capital/profit AFN sums ≥ pre (merge skips existing currency rows; does not double). Investigate if post << pre.

---

## 7. Profit records

```sql
-- PRE source
SELECT
  COUNT(*) AS profit_entries,
  COALESCE(SUM(profit_amount_afn), 0) AS profit_afn
FROM investor_profit_entries
WHERE status <> 'cancelled';

-- POST ledger
SELECT
  COUNT(*) AS profit_txns,
  COALESCE(SUM(amount_afn), 0) AS profit_afn
FROM investor_transactions
WHERE type = 'profit_earned';
```

**Expect:** Post `profit_earned` count ≥ non-cancelled entries (existing txns may already exist). Duplicates probe:

```sql
SELECT investor_id, reference_id, COUNT(*) AS c
FROM investor_transactions
WHERE type = 'profit_earned' AND reference_type = 'sale_item'
GROUP BY investor_id, reference_id
HAVING c > 1;
```

---

## 8. Customer balances

```sql
SELECT
  COUNT(*) AS customers,
  COALESCE(SUM(current_due_afn), 0) AS due_afn
FROM customers
WHERE trash = 0 AND deleted_at IS NULL;

-- POST multi-currency balances
SELECT
  COUNT(*) AS balance_rows,
  COALESCE(SUM(balance_afn), 0) AS balance_afn_sum
FROM customer_balances;

-- Customers with due but no balance row (post 151000)
SELECT c.id, c.current_due_afn
FROM customers c
LEFT JOIN customer_balances cb ON cb.customer_id = c.id
WHERE c.trash = 0
  AND c.current_due_afn <> 0
  AND cb.id IS NULL
LIMIT 100;
```

**Expect:** `customers.current_due_afn` unchanged. AFN balance sum should cover non-zero dues after backfill.

```sql
-- History at risk (PRE only)
SELECT COUNT(*), COALESCE(SUM(amount_afn), 0)
FROM customer_transactions;
```

Export this table before simplify.

---

## 9. Duplicate / missing relationships

```sql
-- POST: orphan investment_id
SELECT COUNT(*) AS orphan_investment_fk
FROM product_batches b
LEFT JOIN investments i ON i.id = b.investment_id
WHERE b.investment_id IS NOT NULL AND i.id IS NULL;

-- POST: investor-funded batch whose investment.investor_id missing user
SELECT COUNT(*) AS orphan_investor_user
FROM product_batches b
JOIN investments i ON i.id = b.investment_id
LEFT JOIN users u ON u.id = i.investor_id
WHERE u.id IS NULL;

-- PRE: investor_id on batches pointing at missing investors
SELECT COUNT(*) AS orphan_batch_investor
FROM product_batches b
LEFT JOIN investors inv ON inv.id = b.investor_id
WHERE b.investor_id IS NOT NULL AND inv.id IS NULL;

-- Allocations pointing at missing batches (PRE)
SELECT COUNT(*) AS bad_alloc
FROM investment_product_allocations a
LEFT JOIN product_batches b ON b.id = a.batch_id
WHERE a.batch_id IS NOT NULL AND b.id IS NULL;
```

---

## 10. Expenses

```sql
SELECT
  COUNT(*) AS expenses,
  COALESCE(SUM(amount_afn), 0) AS expense_afn
FROM expenses
WHERE trash = 0 AND deleted_at IS NULL;

-- POST FX restore
SELECT
  COUNT(*) AS missing_currency,
  COALESCE(SUM(amount_afn), 0) AS afn_of_missing
FROM expenses
WHERE currency_id IS NULL OR currency_code IS NULL;
```

---

## 11. Comparison worksheet

| Metric | Pre | Post | Pass rule |
|--------|-----|------|-----------|
| sum batch remaining_quantity | | | equal |
| sum inventory quantity | | | equal |
| sales_total_afn | | | equal |
| sale_items line_total_afn | | | equal |
| payments_total_afn | | | equal |
| customers due_afn | | | equal |
| expenses_afn | | | equal |
| investment status counts | | | no unexpected mass-close |
| orphan investment_id | n/a | | = 0 |
| duplicate profit_earned | | | = 0 new dupes |
| wallet capital_afn | | | post ≥ pre (explain gaps) |

---

## 12. Application smoke (after migrate)

1. Login as admin  
2. Dashboard loads (no `investment_id` SQL error; no ViteException)  
3. Inventory valuation matches SQL stock value within rounding  
4. Investor detail shows capital/profit  
5. Open a historical sale / payment  
6. Customer due screen matches `current_due_afn` / balances  

Any mismatch → keep maintenance mode; see `PRODUCTION_RECOVERY_PLAN.md`.
