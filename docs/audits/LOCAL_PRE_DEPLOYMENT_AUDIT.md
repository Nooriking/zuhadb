# ZUHA POS — Local Pre-Deployment Audit

**Date:** 2026-11-23  
**Scope:** Local project only (`APP_ENV=local`, `DB_HOST=127.0.0.1`, `DB_DATABASE=zuha`)  
**Production:** Not contacted, not modified.

---

## 1. Project inventory

| Item | Value |
|------|--------|
| App | ZUHA PERFUME / perfume-pos |
| Laravel | 12.69.2 (`laravel/framework ^12.0`) |
| PHP | 8.2.12 (requirement `^8.2`) |
| Database | MySQL @ `127.0.0.1:3306` / database `zuha` (local) |
| Test DB | `zuha_testing` (phpunit.xml; local only) |
| Frontend | Inertia + React + Vite |
| Git branch | `main` (clean working tree at audit start) |
| Tests | Pest/PHPUnit under `tests/Feature`, `tests/Unit` |

### Local DB verification

- `APP_ENV=local`, `APP_URL=http://127.0.0.1:8000`
- `DB_HOST=127.0.0.1`, `DB_DATABASE=zuha`, `DB_USERNAME=root`
- Confirmed **not** a remote/production host

### Migration status (local `zuha`)

- Schema simplify migration **already applied**:  
  `2026_11_22_100000_simplify_product_batch_investment_schema` → Ran  
- Local `product_batches` has `investment_id`, **no** `investor_id`
- Related follow-ups also Ran (`100100`, `140000`, FIFO index, etc.)

---

## 2. Intended ownership architecture

**Final design (code + migrated schema):**

| Concept | Rule |
|---------|------|
| Store-owned batch | `product_batches.investment_id IS NULL` |
| Investor-funded batch | `product_batches.investment_id` → `investments.id` |
| Investor identity | `investments.investor_id` → `users.id` (role=investor) |
| Legacy column | `product_batches.investor_id` **removed** after backfill |
| Stock truth | `product_batches.remaining_quantity` + `inventories` |

Production crash (`Unknown column 'investment_id'`) means production still has the **pre-simplify** schema while application code already queries `investment_id`.

---

## 3. Architecture audit summary

### ProductBatch — OK after local migrate

- Fillable / relations / scopes use `investment_id`
- `valuationSummary()` splits store vs investor via `investment_id`
- Accessor `investor_id` resolves through `investment.investor_id` (no DB column required)
- Soft deletes + `trash` preserved

### Investments / Inventory / Sales / Dashboard

- Purchase/Sale paths write and read `investment_id`
- Dashboard uses `ProductBatch::valuationSummary($branchId)`
- Investor dashboards join `product_batches.investment_id` → `investments.investor_id`

### Confirmed local defects (pre-repair)

1. **Stock transfers** dropped `investment_id` on destination batches (ownership became store).
2. **Migration orphan backfill** reused latest investment per investor (wrong attribution).
3. **InvestorTransactionController** selected dropped columns `investment_number` / `capital_amount_afn`.
4. **UI/legacy aliases** still expected `investment_number` / `capital_amount_*`.
5. **Seeders/tests** still wrote or queried `product_batches.investor_id` / obsolete wallet/profit APIs.
6. **Dead ProductController helpers** still referenced `purchase_item_id` / dropped profit snapshot columns.

---

## 4. Schema comparison (local after migrate)

`product_batches` columns include:  
`investment_id` (nullable FK → investments, ON DELETE SET NULL), purchase metadata, cost snapshots, FIFO index.  
**Absent:** `investor_id`, `purchase_item_id`, FX snapshot profit columns removed by simplify migration.

`investments` slim columns:  
`investor_id`, `currency_id`, `exchange_rate`, `amount_currency`, `amount_afn`, `investment_date`, `status` (`active`|`closed`), notes, soft delete/trash.

Dropped tables (local):  
`investment_product_allocations`, `investment_purchase_allocations`, `investor_profit_entries`, `investor_wallets`, etc.

Integrity probe (local `zuha`): 7 batches, 1 investor-funded, 6 store-owned, **0 orphan** `investment_id` FKs; valuation loads without SQL error.

---

## 5. Migration safety notes

File: `database/migrations/2026_11_22_100000_simplify_product_batch_investment_schema.php`

| Concern | Assessment |
|---------|------------|
| Adds `investment_id` nullable | Correct |
| Backfill from allocations | Correct (preferred path) |
| Orphan `investor_id` batches | **Was unsafe** (latest investment); repaired to dedicated backfill investment per batch |
| Drops `investor_id` | After backfill; data mapped into investments |
| FK drop by fixed name | **Risk on prod** if constraint renamed; repaired with column-based FK discovery |
| `down()` | Irreversible — requires DB backup |
| Destructive | Drops allocation/profit/wallet legacy tables — app must ship with post-simplify code |

---

## 6. Environment rules compliance

- No production SSH / DB / `.env` changes
- No deploy/push
- No `migrate:fresh` / `db:wipe`
- `APP_KEY` untouched
- Local `.env` not modified by this audit

---

## 7. Next steps (executed in repair phase)

See `docs/audits/LOCAL_REPAIR_AND_DEPLOYMENT_READINESS.md` for fixes, tests, and deployment plan.
