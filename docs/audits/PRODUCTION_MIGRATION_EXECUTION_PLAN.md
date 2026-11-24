# Production Migration Execution Plan — Zuha POS

**Date:** 2026-11-23  
**Mode:** Plan only — DO NOT execute against production from this document  
**Companion:** `PRODUCTION_MIGRATION_AUDIT.md`, `PRODUCTION_RECOVERY_PLAN.md`, `PRODUCTION_DATA_INTEGRITY_CHECKS.md`

---

## Verdict (summary)

**NOT SAFE TO DEPLOY** until blocking fixes/preconditions below are satisfied.  
After those: **SAFE AFTER SPECIFIED FIXES** (still requires verified backup + maintenance window).

### Blocking issues (must clear before `migrate --force`)

1. **Fix investment status mapping** in `2026_11_22_100000_simplify_*` — do not close `partially_used` / `fully_used` as `closed` blindly.
2. **Confirm exact Pending list** on production (`php artisan migrate:status`) matches the presumed 46 starting at `2026_10_28_100000_*`.
3. **Full verified backup** (mysqldump or snapshot) + restore drill on a staging copy.
4. **Investor→user preflight** — zero unmatched investors (dry-run mapping); fix email/role collisions first.
5. **Export/archive before drop:** `customer_transactions`, `investor_profit_entries`, `investment_*_allocations`, `investor_wallets`, `investors` (pre-rename).
6. **Verify `investments.investor_id` unique is droppable** before orphan multi-insert (or ensure drop at start of simplify).
7. **Atomic window:** never leave commit `280d3ab` app code live against pre-simplify schema (causes `Unknown column investment_id`). Prefer maintenance mode → backup → migrate → verify → open.

---

## Recommended execution groups

Run as **one maintenance window**, but mentally checkpoint between groups. Stop on any failure (see Stop conditions).

### Group A — Currency foundation (Pending #1–5)

Order (chronological):

1. `2026_10_28_100000_create_currencies_table`
2. `2026_10_28_100100_add_ledger_currency_snapshots_to_sales_and_payments`
3. `2026_10_28_120000_add_product_units_and_multi_currency_money`
4. `2026_10_28_140000_create_product_exchange_rates_table`
5. `2026_10_28_160000_add_purchase_and_sale_fx_snapshots`

**Pre-checks:** `currencies` absent or incomplete; users table exists.  
**Post-checks:** AFN/USD/AED rows exist; `sales.ledger_currency_code` present.

### Group B — FX board + capital uniqueness (Pending #6–11)

6. `2026_11_12_140000_drop_product_unit_conversion_columns`
7. `2026_11_12_150000_business_logic_fx_and_investor_capital` ← drops unique investor_id
8. `2026_11_12_151000_alter_exchange_rates_effective_at_to_datetime`
9. `2026_11_14_120000_add_three_currency_fx_board_snapshots`
10. `2026_11_14_121000_add_batch_unit_cost_currency_columns`
11. `2026_11_14_140000_add_investor_profit_currency_and_gross_profit` ← rewrites sale_items profits

**Pre-checks:** snapshot sale_items profit totals (integrity SQL).  
**Post-checks:** unique on `investments.investor_id` **gone**; `gross_profit_afn` populated; store residual ≈ gross − investor.

### Group C — Investor identity gate (Pending #12–13) — HIGHEST RISK

12. `2026_11_14_160000_migrate_investor_identity_to_users`
13. `2026_11_14_170000_restrict_investor_user_fk_deletes`

**Pre-checks (mandatory):**

```sql
-- counts for operator review (read-only)
SELECT COUNT(*) FROM investors;
SELECT role, COUNT(*) FROM users GROUP BY role;
SELECT i.id, i.email, u.id AS user_id, u.role
FROM investors i
LEFT JOIN users u ON u.id = i.user_id OR u.email = i.email;
```

Resolve collisions offline.  
**Post-checks:** `investors` renamed to `investors_legacy`; `investments.investor_id` references `users.id`; sample joins succeed.

**STOP** if this group throws — restore backup (see recovery plan). Do not continue.

### Group D — Pricing + multi-currency ledgers (Pending #14–27)

14–16 product_prices create/fix/backfill  
17–18 low-risk drops  
19–26 sale return/receipt/customer/wallet/fx tables  
27 catalog currency on variants  

**Post-checks:** `product_prices` row count > 0 if variants have prices; `customer_balances` / `investor_wallet_balances` tables exist (may be empty).

### Group E — Column cleanup + transient drops (Pending #28–38)

Includes duplicate timestamp drops and twins.  
**Pre-check:** product_prices already backfilled.  
**Post-checks:** variant/product legacy price columns gone; batch FX snapshot cols present; `product_exchange_rates` / `fx_gain_losses` absent.

### Group F — Schema simplify + follow-ups (Pending #39–46) — CRITICAL

39. `2026_11_22_100000_simplify_product_batch_investment_schema` (**only after status-mapping fix**)  
40. `2026_11_22_100100_drop_unique_investor_id_from_investments`  
41. `2026_11_22_140000_backfill_product_batch_purchase_currency`  
42. `2026_11_22_151000_add_multi_currency_to_customers`  
43–46 AI, FIFO index, invoice settings, expenses FX  

**Pre-checks:** export tables that will be dropped; confirm status fix deployed in migration file on server.  
**Post-checks:** run full integrity pack; `product_batches.investment_id` exists; `investor_id` column on batches gone; dashboard loads.

---

## Exact order

Use Laravel’s natural order (`php artisan migrate --force`). Do **not** hand-pick out of order except for intentional `--path` recovery after a documented failure (see recovery plan).

Presumed first Pending:

`2026_10_28_100000_create_currencies_table`

Presumed last:

`2026_11_23_140000_restore_multi_currency_on_expenses`

---

## Pre-checks (production, read-only / ops)

1. `php artisan migrate:status` → capture full Pending list; compare to audit list of 46.
2. Confirm app is in **maintenance mode** before schema changes.
3. Confirm deployed code includes repaired simplify migration (`280d3ab` or later with status fix).
4. Backup:

```bash
mysqldump --single-transaction --routines --triggers -u... -p... DBNAME > zuha_prod_YYYYMMDD_HHMM.sql
# verify file size + gunzip/head; ideally restore to staging and SELECT COUNT(*) key tables
```

5. Row-count baseline (see integrity checks doc) for:
   - sales, sale_items, sale_payments
   - product_batches (sum remaining_quantity)
   - inventories
   - investments, investor_transactions, investor_wallets
   - customers.current_due_afn
   - expenses
6. Investor mapping preflight (Group C).
7. Confirm MySQL/MariaDB version and disk space for ALTER TABLE copies.

---

## Suggested migrate invocation (when cleared)

```bash
php artisan down --secret="…"
# backup here
php artisan migrate --force
php artisan optimize:clear
# integrity SQL
php artisan up
```

Optional staged migrate with `--path=` **only** if operators deliberately checkpoint groups after successful prior groups; default is full migrate once.

---

## Post-checks

1. `migrate:status` — zero Pending for the 46.
2. Schema smoke:

```sql
SHOW COLUMNS FROM product_batches LIKE 'investment_id';
SHOW COLUMNS FROM product_batches LIKE 'investor_id';  -- expect empty
SHOW COLUMNS FROM investments LIKE 'amount_afn';
SHOW TABLES LIKE 'investor_wallets';           -- expect empty
SHOW TABLES LIKE 'customer_balances';          -- expect present
```

3. Application smoke: login → dashboard → inventory valuation → investor show → one sale dry-run / read-only pages.
4. Full integrity SQL pack from `PRODUCTION_DATA_INTEGRITY_CHECKS.md`.
5. Compare pre/post row counts for sales/batches/inventory (must match or documented deltas only from intentional investment inserts).

---

## Stop conditions

| Condition | Action |
|-----------|--------|
| Pending list ≠ presumed 46 / unexpected Ran mid-chain | **STOP** — re-audit; do not migrate |
| Backup missing or unrestorable | **STOP** |
| Investor identity migration throws | **STOP** — restore backup |
| Unique constraint error inserting investments | **STOP** — restore or specialist repair; do not force |
| Any migration error after simplify DDL started | **STOP** — treat as partial migration; restore preferred |
| Post-check orphan `investment_id` FKs > 0 | **STOP** open traffic; investigate |
| Dashboard still errors on investment_id | Schema incomplete — stay in maintenance |
| Sale/inventory totals drift beyond expected investment inserts | **STOP** — restore |

---

## What not to do

- Do not `migrate:fresh`, `db:wipe`, truncate business tables, or reseed production.
- Do not manually insert rows into `migrations` to “mark done.”
- Do not delete duplicate migration files to make status look clean.
- Do not roll back code alone after simplify has run.
- Do not modify `.env` / `APP_KEY` as part of this migration.
