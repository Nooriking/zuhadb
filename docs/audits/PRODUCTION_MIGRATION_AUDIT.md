# Production Migration Audit — Zuha POS

**Date:** 2026-11-23  
**Scope:** READ-ONLY analysis of migration source code + local schema evidence  
**Production:** Not contacted, not modified, migrations not executed  
**Code baseline:** Commit `280d3ab` (pulled on production per brief)

---

## Evidence boundaries

| Claim | Evidence |
|-------|----------|
| Local schema after all migrations | Direct introspection of local `zuha` DB (see `docs/audits/_local_schema_snapshot.txt`) |
| Pending set ≈ 46 migrations | Filename count of last 46 migrations after presumed last-ran `2026_10_06_160000_*`; matches operator report of ~46 Pending |
| Production has `investor_id`, lacks `investment_id` | Operator-reported SQL error + prior local audit; **not** verified live |
| Exact production `migrations` table contents | **Unknown** — must be confirmed with `php artisan migrate:status` on production before any run |

**Assumption used throughout (MUST VERIFY):** Production last Ran = `2026_10_06_160000_move_return_type_to_sale_return_items_expense_bearer`. All later migrations are Pending.

If production diverged (partial apply, manual DDL, different cutoff), this audit’s ordering still applies but the pending membership must be re-scoped.

---

## Presumed pending set (46)

| # | Migration | Risk | Classification | Tables / columns |
|---|-----------|------|----------------|------------------|
| 1 | `2026_10_28_100000_create_currencies_table` | MEDIUM | Safe additive + Data backfill | Creates/alters `currencies`; seeds AFN |
| 2 | `2026_10_28_100100_add_ledger_currency_snapshots_to_sales_and_payments` | LOW | Safe additive | `sales`, `sale_payments` ledger_* columns |
| 3 | `2026_10_28_120000_add_product_units_and_multi_currency_money` | MEDIUM | Safe additive + Dependency-sensitive | Seeds USD/AED; money cols on variants, investments, investor_* tables |
| 4 | `2026_10_28_140000_create_product_exchange_rates_table` | LOW | Safe additive | Creates `product_exchange_rates` (later dropped) |
| 5 | `2026_10_28_160000_add_purchase_and_sale_fx_snapshots` | MEDIUM | Safe additive + Data backfill | Purchase/sale FX snapshot cols; backfills from existing rates only |
| 6 | `2026_11_12_140000_drop_product_unit_conversion_columns` | MEDIUM | Destructive | Drops `units_per_pack`, `pack_unit_name`, `base_unit_name` |
| 7 | `2026_11_12_150000_business_logic_fx_and_investor_capital` | HIGH | Safe additive + Destructive (index) + Dependency-sensitive | Recreate/alter `exchange_rates`; **drops unique(`investments.investor_id`)**; enum expand; allocation FK |
| 8 | `2026_11_12_151000_alter_exchange_rates_effective_at_to_datetime` | LOW | Data transformation | `exchange_rates.effective_at` type |
| 9 | `2026_11_14_120000_add_three_currency_fx_board_snapshots` | LOW | Safe additive | FX board cols on products/sales/sale_items/investments |
| 10 | `2026_11_14_121000_add_batch_unit_cost_currency_columns` | LOW | Safe additive | `product_batches.unit_cost_currency`, `exchange_rate` |
| 11 | `2026_11_14_140000_add_investor_profit_currency_and_gross_profit` | **HIGH** | Safe additive + **Data transformation** | Batch/product profit currency cols; **rewrites `sale_items.store_profit_afn`** |
| 12 | `2026_11_14_160000_migrate_investor_identity_to_users` | **CRITICAL** | Data transformation + Destructive + Potentially irreversible + Dependency-sensitive | Remaps all `investor_id` FKs → `users.id`; renames `investors` → `investors_legacy` |
| 13 | `2026_11_14_170000_restrict_investor_user_fk_deletes` | MEDIUM | Dependency-sensitive | Repoints investor FKs to `users` with RESTRICT/SET NULL |
| 14 | `2026_11_18_000000_create_product_prices_table` | LOW | Safe additive | Creates `product_prices` |
| 15 | `2026_11_18_000100_fix_currency_catalog_rates` | MEDIUM | Data backfill | Overwrites AFN/USD/AED `rate_to_default` to catalog defaults |
| 16 | `2026_11_18_000200_backfill_product_prices` | MEDIUM | Data backfill | Upserts prices from variant columns (must run **before** variant price drops) |
| 17 | `2026_11_18_140700_drop_logo_from_brands_table` | LOW | Destructive | Drops `brands.logo` |
| 18 | `2026_11_18_210000_drop_minimum_stock_from_product_variants_table` | LOW | Destructive | Drops `product_variants.minimum_stock` |
| 19 | `2026_11_18_220000_add_multi_currency_to_sale_returns` | LOW | Safe additive | Return FX columns |
| 20 | `2026_11_18_220100_add_multi_currency_to_sale_return_items` | LOW | Safe additive | Return item FX columns |
| 21 | `2026_11_18_220200_add_multi_currency_to_receipts` | LOW | Safe additive | Receipt FX columns |
| 22 | `2026_11_18_220300_add_multi_currency_to_customer_transactions` | LOW | Safe additive | Adds FX cols if table exists (later dropped) |
| 23 | `2026_11_18_220400_create_customer_balances_table` | LOW | Safe additive | Creates `customer_balances` |
| 24 | `2026_11_18_220500_create_investor_wallet_balances_table` | MEDIUM | Safe additive + Dependency-sensitive | Creates `investor_wallet_balances` (**FK → users**) |
| 25 | `2026_11_18_220600_create_fx_gain_losses_table` | LOW | Safe additive | Creates `fx_gain_losses` (later dropped) |
| 26 | `2026_11_18_220700_disable_stale_exchange_rates` | MEDIUM | Data transformation | Soft-trash rates `< 1000` (non-identical pair) |
| 27 | `2026_11_19_150000_add_unified_catalog_currency_to_product_variants` | MEDIUM | Safe additive + Data backfill | Catalog currency + USD/AED amount cols; backfill |
| 28 | `2026_11_20_195855_drop_product_exchange_rates_table` | MEDIUM | Destructive + Potentially irreversible | Drops `product_exchange_rates` |
| 29 | `2026_11_20_201400_drop_duplicate_price_and_relation_columns_from_products_table` | **HIGH** | Destructive + Potentially irreversible + **Duplicate/conflicting** | Drops money/relation cols from `products` incl. `investor_id`, `variant_id` |
| 30 | `2026_11_20_201400_drop_duplicate_price_columns_from_product_variants_table` | **HIGH** | Destructive + Potentially irreversible + **Duplicate timestamp** | Drops price cols from `product_variants` |
| 31 | `2026_11_20_201401_add_fx_snapshot_columns_to_product_batches_table` | LOW | Safe additive + Duplicate twin | Batch FX board cols |
| 32 | `2026_11_20_201421_drop_duplicate_price_columns_from_product_variants_table` | LOW | Duplicate/conflicting (idempotent twin of #30) | No-op if already dropped |
| 33 | `2026_11_20_201422_drop_duplicate_price_and_relation_columns_from_products_table` | LOW | Duplicate/conflicting (idempotent twin of #29) | No-op if already dropped |
| 34 | `2026_11_20_201423_add_fx_snapshot_columns_to_product_batches_table` | LOW | Duplicate twin of #31 | Idempotent hasColumn guards |
| 35 | `2026_11_20_201742_add_rate_snapshot_to_sale_payments_table` | LOW | Safe additive | `rate_effective_at`, `rate_source` on payments |
| 36 | `2026_11_20_201743_add_cogs_reversal_to_sale_return_items_table` | LOW | Safe additive | COGS reversal cols |
| 37 | `2026_11_20_210000_drop_fx_gain_losses_table` | MEDIUM | Destructive | Drops `fx_gain_losses` |
| 38 | `2026_11_20_210100_drop_duplicate_columns_from_sale_items` | MEDIUM | Destructive | Drops duplicate FX amount cols on sale_items |
| 39 | `2026_11_22_100000_simplify_product_batch_investment_schema` | **CRITICAL** | See §3 deep audit | ownership rewrite, table drops, column drops |
| 40 | `2026_11_22_100100_drop_unique_investor_id_from_investments` | HIGH | Destructive (index) + Dependency-sensitive | Safety net if unique still present |
| 41 | `2026_11_22_140000_backfill_product_batch_purchase_currency` | MEDIUM | Data backfill | Infers `purchase_currency_id` from rates |
| 42 | `2026_11_22_151000_add_multi_currency_to_customers` | MEDIUM | Safe additive + Data backfill | Ensures `customer_balances`; backfills AFN dues |
| 43 | `2026_11_22_203500_create_ai_conversations_and_messages_tables` | LOW | Safe additive | AI tables |
| 44 | `2026_11_22_210000_add_product_batches_fifo_lookup_index` | LOW | Safe additive | FIFO indexes |
| 45 | `2026_11_23_120000_add_invoice_fields_to_app_settings_table` | LOW | Safe additive | Invoice fields on app_settings |
| 46 | `2026_11_23_140000_restore_multi_currency_on_expenses` | MEDIUM | Safe additive + Data backfill | Restores expense FX cols; backfills AFN |

---

## Duplicate / conflicting migrations

### Same timestamp `2026_11_20_201400` (two files)

Laravel orders by full filename. Alphabetical order:

1. `...201400_drop_duplicate_price_and_relation_columns_from_products_table`
2. `...201400_drop_duplicate_price_columns_from_product_variants_table`

**Risk:** Same-timestamp pairs are fragile across tools; twins `201421`/`201422` exist as idempotent backups. Do not delete either file.

### Intentional twins (safe no-ops after first)

| Original | Twin |
|----------|------|
| `201400` products drop | `201422` |
| `201400` variants drop | `201421` |
| `201401` batch FX | `201423` |

### Transient create-then-drop

| Create | Drop |
|--------|------|
| `10_28_140000` product_exchange_rates | `11_20_195855` |
| `11_18_220600` fx_gain_losses | `11_20_210000` |

Expected final state: both tables **absent**. Not dangerous if chain completes.

---

## Dependency graph (logical, not merely chronological)

```
currencies (10_28_100000)
  └─ money columns / FX snapshots (10_28_*)
       └─ exchange_rates restore + drop unique investor_id (11_12_150000)  ← MUST before multi-investment inserts
            └─ batch unit cost currency (11_14_121000)
                 └─ sale_items gross/store profit rewrite (11_14_140000)
                      └─ investor identity → users (11_14_160000)  ← CRITICAL GATE
                           └─ FK restrict (11_14_170000)
                                └─ product_prices create + backfill (11_18_000*)  ← BEFORE variant price drops
                                     └─ customer_balances / investor_wallet_balances (11_18_2204/2205)
                                          └─ catalog cols (11_19) then DROP duplicate price cols (11_20_2014*)
                                               └─ simplify schema (11_22_100000)  ← CRITICAL
                                                    └─ drop unique safety (100100)
                                                         └─ purchase currency backfill (140000)
                                                              └─ customer balance backfill (151000)
                                                                   └─ FIFO / AI / settings / expenses
```

**Chronological order is correct for this chain** — do **not** reorder files. The dependency notes above explain *why* order matters and which failures cascade.

---

## Deep audit: `2026_11_22_100000_simplify_product_batch_investment_schema`

### What it does (ordered)

1. **Additive:** `product_batches.investment_id` (+ purchase metadata columns)
2. **Backfill:** purchase fields from `products` via legacy `purchase_item_id`
3. **Additive + backfill:** `investments.amount_currency` / `amount_afn` from `capital_amount_*`
4. **Data transformation (BLOCKING):** status normalize — see below
5. **Backfill:** batch→investment via `investment_product_allocations`
6. **Backfill:** orphan `investor_id` batches → **dedicated** investment per batch (repaired; no “latest investment” reuse)
7. **Backfill:** `investor_wallets` → `investor_wallet_balances` (skip if row exists)
8. **Backfill:** `investor_profit_entries` → `investor_transactions` (dedupe by investor+type+sale_item)
9. **Destructive:** drop FKs/cols on batches (`investor_id`, `purchase_item_id`, profit FX snapshot cols)
10. **Destructive:** slim `investments` (drop capital_*, investment_number, source_type, FX board cols, user_id, …)
11. **Destructive:** drop purchase shell cols on `products`; drop `product_variants.stock_quantity`
12. **Destructive irreversible:** drop tables:
    - `investor_profit_entries`
    - `investment_product_allocations`
    - `investment_purchase_allocations`
    - `investor_wallets`
    - `customer_transactions` ← **history loss**
    - `investor_user_migration_map`
    - `investors_legacy`
13. **Intentionally NOT dropped:** `customer_balances` (fixed in `280d3ab`)

### Checklist results

| Check | Result |
|-------|--------|
| Referenced columns exist at point of use | **Mostly yes** with `hasColumn`/`hasTable` guards. Requires prior migrations to have added money/FX cols. `purchase_item_id` / `investor_id` expected on production pre-simplify. |
| Backfill from allocations | Correct; only fills `whereNull(investment_id)` |
| Investor attribution | **Fixed in 280d3ab** — dedicated investment per orphan batch; notes tag source batch id |
| Duplicate investments on retry | If migration **fails after inserts** but before batch update / before migrations row recorded: orphan loop can create **extra** investments on rerun (partially mitigated by `whereNull(investment_id)` on batch update — new investments may be orphaned capital rows) |
| Wallet transfer duplicates | Guarded by existing `(investor_id, currency_id)` lookup — **safe** |
| Profit transaction duplicates | Guarded by exists() on investor+type+reference — **safe** |
| Existing records silently lost | Table drops destroy allocation/profit/wallet/customer_transaction history after copy. **customer_transactions has no copy step** |
| Status normalization | **UNSAFE** — see blocking issue #1 |
| Safe on production schema | Depends on: currencies exist, investor_id remapped to users, unique(investor_id) already dropped, allocation tables present |
| Assumptions from local DB | Does not hardcode local IDs; uses Schema guards. Still assumes production followed the same migration path |

### Blocking issue #1 — Status normalization

Source (simplify migration):

```php
DB::table('investments')
    ->whereNotIn('status', ['active', 'closed'])
    ->update(['status' => 'closed']);
```

Original enum (`2026_09_11_170232`):

- `active`
- `partially_used`
- `fully_used`
- `withdrawn`
- `closed`

**Effect:** every `partially_used` / `fully_used` / `withdrawn` investment becomes `closed`.

`partially_used` and `fully_used` are **valid open capital states** on production. Closing them is incorrect business-state corruption.

**Required fix before production migrate:** map explicitly, e.g.:

| From | To |
|------|-----|
| `active` | `active` |
| `partially_used` | `active` |
| `fully_used` | `active` (or `closed` only if product policy says allocated-complete = closed) |
| `withdrawn` | `closed` |
| `closed` | `closed` |

Do **not** ship the current blanket `whereNotIn → closed`.

### Blocking issue #2 — Unique `investments.investor_id` during orphan inserts

- `2026_10_06_113649` added unique(`investor_id`) (presumed **Ran** on production).
- `2026_11_12_150000` tries to drop it (Pending).
- Simplify may insert **multiple** investments per investor for orphan batches.
- `2026_11_22_100100` drops unique again **after** simplify.

If `150000`’s drop silently fails and unique remains, simplify **fails mid-flight** after partial DDL/data changes (MySQL DDL is non-transactional).

**Required pre-check / harden:** verify unique absent before orphan insert loop; fail fast with clear message.

### Blocking issue #3 — `customer_transactions` hard drop

App code no longer references `CustomerTransaction` (grep clean). Production table may still hold payment/due history.

Drop without archive = **irreversible history loss** (balances may survive on `customers.current_due_afn` / later `customer_balances`, but ledger lines vanish).

**Required ops step:** mysqldump `customer_transactions` (and preferably all tables listed in §12) before migrate.

### Blocking issue #4 — Investor identity migration can abort

`2026_11_14_160000` throws if:

- investor email matches a non-investor user
- duplicate user mapping
- orphan `investor_id` values cannot map

Abort mid-swap can leave `investor_user_id` columns and half-remapped FKs. Recovery = restore backup.

**Required pre-check:** dry-run mapping counts; resolve unmatched investors manually first.

### Blocking issue #5 — `sale_items` store_profit rewrite partial failure

`2026_11_14_140000`:

1. `gross_profit_afn = store_profit_afn` where gross≈0  
2. `store_profit_afn = gross − investor_profit`

If interrupted after (1) but before (2) and somehow re-run with gross already set incorrectly, residual can be double-adjusted. Prefer single transactional SQL or verify row counts before/after.

---

## Per-migration failure & idempotency notes

| Pattern | Migrations | Retry? | Half-fail? | Duplicate data risk |
|---------|------------|--------|------------|---------------------|
| hasTable/hasColumn guarded CREATE/ADD | Most additive | Yes | Low (DDL committed) | Low |
| Upsert backfills | prices, wallets, profits | Yes | Medium | Low (guards) |
| Destructive DROP COLUMN/TABLE | 11_12_140000, 11_20_*, simplify | No useful down | **High** | N/A |
| Investor identity remap | 11_14_160000 | **Unsafe** | **Critical** | Can create users |
| Status normalize (current) | simplify | Unsafe | Corrupts state | N/A |
| Orphan investment create | simplify | Partial risk | High | Orphan investment rows |
| Unique drop | 150000, 100100 | Yes | Medium | Low |
| Rate overwrite | 000100 | Overwrites catalog rates | Low | Re-applies defaults |
| Stale FX trash | 220700 | Re-trashes | Low | Low |

**MySQL/MariaDB:** `Schema::table` / `ALTER` / `DROP` auto-commit. Laravel migration transactions do **not** roll back DDL. Any failure after DDL requires backup restore or careful manual repair — never `migrate:fresh`.

---

## Risk summary for production data domains

| Domain | At risk? | How |
|--------|----------|-----|
| Sales totals / sale_items money | Medium | store/gross rewrite; column drops of duplicate FX fields only |
| Purchases / batches qty | Low for qty; High for ownership | simplify remaps ownership; does not truncate qty |
| Inventory quantities | Low | inventories untouched |
| Investor capital | **High** | identity remap; amount_* backfill; status close; unique constraint; orphan investments |
| Wallet balances | Medium | merge guarded; old wallets table dropped after copy |
| Profit records | Medium | copied then source table dropped |
| Customer balances | Medium | preserved via customer_balances path; **transactions history dropped** |
| Expenses | Low | additive restore |

---

## Verdict contribution

This audit alone finds **code-level blockers** (status normalization) and **ops-level blockers** (backup, identity preflight, unique index, history export). See final verdict in the execution plan companion.
