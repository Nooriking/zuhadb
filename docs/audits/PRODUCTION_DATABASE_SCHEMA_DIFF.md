# Production vs Local Schema Diff — Zuha POS

**Date:** 2026-11-23  
**Production:** **Not introspected** (read-only policy). Diff is **inferred** from migration chain + operator symptoms.  
**Local:** Direct `SHOW COLUMNS` on `zuha` after all migrations Ran (snapshot: `docs/audits/_local_schema_snapshot.txt`).

---

## How to read this document

| Label | Meaning |
|-------|---------|
| **EXPECTED** | Difference explained by pending migrations; resolves if full chain succeeds |
| **DANGEROUS** | Difference that can corrupt data, break FKs, or lose history if mishandled |
| **UNVERIFIED** | Must be confirmed on production with `SHOW CREATE TABLE` / `information_schema` |

Until production schema is exported read-only, treat every production column claim as **UNVERIFIED** except the operator-confirmed `investment_id` absence / `investor_id` presence on batches.

---

## Assumed production baseline

Last Ran (assumed): `2026_10_06_160000_move_return_type_to_sale_return_items_expense_bearer`

Implications:

- Currency catalog tables were previously dropped by `2026_09_22_180000` → **no modern `currencies`** until Pending #1
- `product_batches` has **`investor_id`**, not **`investment_id`** (matches 500 / SQL error)
- Unique on `investments.investor_id` likely **present** (`2026_10_06_113649` Ran)
- `investors` table still primary identity store
- No `product_prices`, `customer_balances`, `investor_wallet_balances`
- Legacy allocation / profit / wallet tables still present

---

## Tables

| Table | Local (post-migrate) | Assumed production | Classification |
|-------|----------------------|--------------------|----------------|
| `currencies` | Present (AFN/USD/AED…) | Absent or stale pre-drop remnant | EXPECTED → recreate |
| `exchange_rates` | Present (datetime effective_at) | Likely absent / old shape | EXPECTED |
| `product_prices` | Present | Absent | EXPECTED |
| `customer_balances` | Present | Absent | EXPECTED |
| `investor_wallet_balances` | Present | Absent | EXPECTED |
| `ai_conversations` / `ai_messages` | Present | Absent | EXPECTED |
| `investor_wallets` | **Absent** | Present | EXPECTED drop after merge — **DANGEROUS** without backup |
| `investor_profit_entries` | **Absent** | Present | EXPECTED drop after copy — **DANGEROUS** without backup |
| `investment_product_allocations` | **Absent** | Present | EXPECTED drop after link — **DANGEROUS** |
| `investment_purchase_allocations` | **Absent** | Present | EXPECTED drop |
| `customer_transactions` | **Absent** | Present | EXPECTED drop — **DANGEROUS** (no copy) |
| `investors` | Absent (legacy archived then dropped) | Present | EXPECTED rename→drop |
| `investors_legacy` | Absent (dropped by simplify) | Absent initially | Transient |
| `product_exchange_rates` | Absent | Absent → created → dropped | Transient EXPECTED |
| `fx_gain_losses` | Absent | Absent → created → dropped | Transient EXPECTED |
| Core POS (`sales`, `sale_items`, `product_batches`, `inventories`, …) | Present | Present | Must remain |

---

## Critical column diffs — `product_batches`

| Column | Local | Assumed prod | Notes |
|--------|-------|--------------|-------|
| `investment_id` | **YES** nullable FK→investments | **NO** | **DANGEROUS mismatch with app code** — root of prod 500 |
| `investor_id` | **NO** | **YES** | Removed by simplify after backfill |
| `purchase_item_id` | NO | YES (likely) | Dropped after metadata copy |
| `invoice_number`, `purchase_date`, `payment_status`, `bill_image`, `notes` | YES | NO | Added by simplify |
| `purchase_currency_id`, `purchase_amount_currency` | YES | NO | Added by simplify + backfill |
| `unit_cost_currency`, `exchange_rate` | YES | Maybe missing | Added 11_14_121000 |
| `profit_per_unit_usd/aed`, `profit_currency_*`, FX board cols | **NO** (dropped by simplify) | Added then dropped mid-chain | Transient |
| `remaining_quantity`, `quantity`, `unit_cost_afn` | YES | YES | Must not change meaning |

---

## Critical column diffs — `investments`

| Column | Local | Assumed prod | Notes |
|--------|-------|--------------|-------|
| `amount_currency`, `amount_afn` | YES | NO | Backfilled from capital_* |
| `capital_amount_*`, `available_*`, `profit_amount_*`, `allocated_*`, `withdrawn_*` | NO | YES | Dropped by simplify |
| `investment_number`, `source_type`, `parent_investment_id` | NO | YES | Dropped |
| `status` enum | `active\|closed` | `active\|partially_used\|fully_used\|withdrawn\|closed` | **DANGEROUS** if blindly closed |
| `investor_id` target | `users.id` | `investors.id` | Remapped by 11_14_160000 |
| Unique(`investor_id`) | NO | YES (likely) | Must drop before multi-investments |

---

## Products / variants / prices

| Area | Local | Assumed prod | Classification |
|------|-------|--------------|----------------|
| Catalog prices on variants (`sale_price_afn`, …) | **Dropped** | Present | EXPECTED move → `product_prices` |
| `products.investor_id` / `variant_id` / purchase FX cols | Dropped | Present | EXPECTED drop **after** price backfill |
| `product_variants.stock_quantity` | Dropped | Maybe present | EXPECTED; inventories are SoT |
| `product_prices` | Present | Absent | EXPECTED |

**DANGEROUS order violation:** dropping variant price columns **before** `000200_backfill_product_prices` would lose catalog prices. Chronological order is correct — do not reorder.

---

## Sales / payments / returns

| Area | Local | Assumed prod | Classification |
|------|-------|--------------|----------------|
| Ledger currency snapshots on sales/payments | Present | Absent | EXPECTED additive |
| Sale FX board + equivalents | Present | Absent | EXPECTED |
| `sale_items.gross_profit_afn` | Present | Absent | EXPECTED; store_profit meaning changes |
| Duplicate sale_item FX amount cols | Dropped | May appear mid-chain then drop | EXPECTED |
| Sale return / receipt multi-currency cols | Present | Absent | EXPECTED |
| Payment `rate_effective_at` / `rate_source` | Present | Absent | EXPECTED |

---

## Customers / investors / wallets

| Area | Local | Assumed prod | Classification |
|------|-------|--------------|----------------|
| `customer_balances` | Present | Absent | EXPECTED; backfill from `current_due_afn` |
| `customer_transactions` | Absent | Present | **DANGEROUS drop** |
| `investor_wallet_balances` | Present | Absent | EXPECTED; FK→users |
| `investor_wallets` | Absent | Present | Merge then drop |
| `investor_transactions` type enum | includes `goods_deposit` | older enum | EXPECTED expand |

---

## Indexes & foreign keys (high signal)

| Object | Local | Assumed prod | Risk |
|--------|-------|--------------|------|
| `product_batches_investment_id_foreign` | Present NULL ON DELETE | Absent | EXPECTED add |
| `product_batches_investor_id_foreign` | Absent | Present | Dropped in simplify |
| Unique `investments.investor_id` | Absent | Present | **DANGEROUS** if still present at orphan insert |
| FIFO indexes on batches | Present | Absent | EXPECTED additive |
| Investor FKs → users RESTRICT | Present | → investors CASCADE/etc. | Remap required |

---

## Nullable / defaults / types

| Item | Concern |
|------|---------|
| `product_batches.investment_id` nullable | NULL = store-owned — correct |
| `investments.amount_afn` default 0 | Backfill must run before capital_* drop |
| `investments.status` ENUM shrink | Requires data remap **before** MODIFY ENUM or MySQL rejects/truncates |
| Expense `currency_id` RESTRICT delete | Added late; AFN backfill first |

**ENUM shrink risk:** simplify runs `ALTER … ENUM('active','closed')` **after** updating non-matching statuses to `closed`. If status fix maps `partially_used`→`active` first, MODIFY succeeds. If any unexpected status remains, ALTER fails mid-migration.

---

## Required relationships (final intended)

```
users (role=investor)
  ↑ investor_id
investments
  ↑ investment_id (nullable)
product_batches → inventories (stock SoT)
                 → sale_items.batch_id

customer_balances.customer_id → customers
customer_balances.currency_id → currencies

investor_wallet_balances.investor_id → users
investor_wallet_balances.currency_id → currencies

product_prices → products / product_variants / currencies
```

---

## Dangerous vs expected — operator checklist

Confirm on production (read-only) before migrate:

```sql
-- 1) Ownership crash root
SHOW COLUMNS FROM product_batches LIKE 'investment_id';
SHOW COLUMNS FROM product_batches LIKE 'investor_id';

-- 2) Identity model
SHOW TABLES LIKE 'investors';
SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'investments' AND COLUMN_NAME = 'investor_id';

-- 3) Unique blocker
SHOW INDEX FROM investments WHERE Column_name = 'investor_id';

-- 4) History tables that will disappear
SHOW TABLES LIKE 'customer_transactions';
SHOW TABLES LIKE 'investor_profit_entries';
SHOW TABLES LIKE 'investor_wallets';
SHOW TABLES LIKE 'investment_product_allocations';

-- 5) Currency presence
SHOW TABLES LIKE 'currencies';
```

Export results into the change ticket. Any unexpected “already migrated” partial state elevates risk to **restore-only recovery**.

---

## Local schema snapshot (authoritative for “target”)

See `docs/audits/_local_schema_snapshot.txt` for full local column listings of key tables after successful migration of commit `280d3ab` chain.
