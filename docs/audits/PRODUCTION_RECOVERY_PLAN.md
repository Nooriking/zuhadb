# Production Recovery Plan — Zuha POS Migrations

**Date:** 2026-11-23  
**Principle:** Prefer restore-from-backup over surgical DDL undo. Simplify `down()` throws by design.

---

## 1. Backup restoration (primary recovery)

### Before any migrate

1. Full logical backup:

```bash
mysqldump --single-transaction --routines --triggers \
  -u"$DB_USER" -p"$DB_PASS" "$DB_DATABASE" \
  > "/backups/zuha_prod_pre_migrate_$(date +%Y%m%d_%H%M%S).sql"
```

2. Optional physical snapshot (host/volume) for faster rollback.
3. Verify: file non-empty; restore to **staging** DB; `SELECT COUNT(*)` on `sales`, `product_batches`, `investments` matches production.
4. Keep app in maintenance mode until post-checks pass.

### Restore procedure (production)

```bash
php artisan down
# stop queue workers / cron that write DB
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_DATABASE" < /backups/zuha_prod_pre_migrate_TIMESTAMP.sql
php artisan optimize:clear
# redeploy previous app release if code was updated
php artisan up
```

**Never** restore only some tables unless a DBA proves FK/order integrity — preferential full DB restore.

---

## 2. Rollback strategy by layer

| Layer | If migrate **not** started | If migrate **partial** | If migrate **complete** |
|-------|----------------------------|------------------------|-------------------------|
| App code | Keep old release | Prefer restore DB + old or new code consistently | New code required (needs `investment_id`) |
| DB | N/A | **Restore backup** | Restore backup to roll back; or accept forward-only |
| Assets (`public/build`) | Keep paired with code | Pair with restored code | Keep `280d3ab` build |

**Hard rule:** After simplify succeeds, rolling back **code only** reintroduces `investor_id` expectations against a schema that dropped it — broken either way. Always restore DB if rolling back past simplify.

---

## 3. Partial-migration scenarios

### A. Failure in Group A–B (currency / FX additive)

**Symptoms:** Missing column errors; currencies half-seeded.  
**Recovery:**

1. Capture `migrate:status` + error log.
2. Prefer **restore backup** (cleanest).
3. If additive-only and no data rewrite yet, skilled repair may `migrate` retry after fixing precondition — still prefer restore if unsure.

### B. Failure in Group C — investor identity (`11_14_160000`)

**Highest danger.** Possible leftover state:

- `investor_user_id` columns present
- Some tables remapped, others not
- `investors` renamed to `investors_legacy` or not
- New users created for investors

**Recovery:** **Restore backup only.** Do not hand-edit FKs. Fix unmatched investors on a restored staging copy, then re-attempt.

### C. Failure during `sale_items` profit rewrite (`11_14_140000`)

**Symptoms:** `gross_profit_afn` half-filled; `store_profit_afn` inconsistent.  
**Recovery:** Restore backup. Do not re-run UPDATE blindly without verifying:

```sql
SELECT
  SUM(store_profit_afn) AS store_sum,
  SUM(investor_profit_afn) AS inv_sum,
  SUM(COALESCE(gross_profit_afn,0)) AS gross_sum
FROM sale_items;
```

### D. Failure mid-simplify (`11_22_100000`)

Possible partial outcomes:

| Progress | State | Recovery |
|----------|-------|----------|
| Added `investment_id`, no backfill | Column exists, mostly NULL | Restore preferred; or complete remaining steps carefully on staging clone first |
| Backfill investments inserted, batches not linked | Orphan investment rows | Restore preferred |
| Batches linked, columns not dropped | Dual `investor_id` + `investment_id` | Can continue forward if data verified |
| Columns dropped, tables not dropped | Inconsistent legacy tables remain | Forward-complete or restore |
| Tables dropped, migration row not recorded | Data irreversible without backup | **Restore only** |
| Status wrongly closed | Business state wrong | Restore or targeted UPDATE from backup export of id→status |

MySQL DDL is **not** transaction-safe with DML in the same migration. Assume auto-commit after each ALTER.

### E. Unique constraint failure on orphan investment insert

**Symptoms:** `Duplicate entry ... for key investments_investor_id_unique`  
**Recovery:**

1. Do not force inserts.
2. Restore backup **or** (if still pre-destructive drops) manually drop unique then resume only with DBA oversight after verifying what was written.
3. Ensure `11_12_150000` / `11_22_100100` unique drop logic is confirmed.

### F. Duplicate twin migrations / same timestamp

Twins are idempotent. Failure usually means first drop failed (FK name). Capture `SHOW CREATE TABLE`; drop FK by actual name; retry. Prefer restore if unsure.

---

## 4. Forward-fix vs restore decision tree

```
Was a destructive DROP TABLE / DROP COLUMN committed?
  ├─ YES → Prefer RESTORE unless you have exact compensating DDL + data export
  └─ NO
       Was investor identity half-applied?
         ├─ YES → RESTORE
         └─ NO
              Additive-only failure with clear fix?
                ├─ YES → Fix precondition, retry migrate
                └─ NO → RESTORE
```

---

## 5. Code / schema coupling after success

| DB state | Compatible code |
|----------|-----------------|
| Pre-simplify (`investor_id` on batches) | Pre-`280d3ab` ownership code |
| Post-simplify (`investment_id`) | `280d3ab`+ |

Split deploy (new code + old DB) is the current production failure mode. Recovery for that **without** migrate: redeploy previous release **or** complete migrate under maintenance.

---

## 6. Post-restore validation

1. `migrate:status` matches pre-attempt baseline.
2. Integrity SQL baseline counts match backup ticket.
3. Login + dashboard with **matching** code release.
4. Spot-check one sale, one investor capital figure, one inventory quantity.

---

## 7. Communication checklist

- [ ] Maintenance announced  
- [ ] Backup path + checksum recorded  
- [ ] Decision owner named for restore vs forward-fix  
- [ ] RTO target agreed (restore time from staging drill)  
- [ ] No `migrate:fresh` / wipe / seeder on production under any recovery path  
