# Non-Functional Requirements (NFR) — Zuha Perfume POS

Laravel 12 + Inertia React + MySQL. Status of each NFR vs the current codebase.

Legend: **Done** = implemented · **Partial** = mostly there · **Ops** = deployment/ops responsibility · **Planned** = not required for core POS day-to-day

---

## 1. Performance

| Requirement | Status | Notes |
|---|---|---|
| Dashboard load &lt; 3s | Partial | Lazy charts + deferred notifications; production needs `npm run build` + OPcache |
| API response &lt; 500ms | Partial | Search/barcode endpoints are lean; keep indexes maintained |
| Product/barcode search &lt; 1s | Done | Unique barcode index + SearchController |
| ≥ 50 concurrent users | Partial | Inventory decrease uses `lockForUpdate`; scale with PHP-FPM + MySQL tuning |
| Pagination on large lists | Done | Index pages use `paginate(15–30)` |

## 2. Security

| Requirement | Status | Notes |
|---|---|---|
| Authentication | Done | Session auth + optional Google OAuth + 2FA TOTP |
| RBAC | Done | Roles/modules/permissions in `User` + middleware |
| Password hashing (bcrypt) | Done | `hashed` cast / BCRYPT_ROUNDS=12 |
| CSRF | Done | Laravel + Inertia XSRF |
| XSS | Done | React escaping; avoid raw HTML except trusted mail/QR |
| SQL Injection | Done | Eloquent / query builder |
| Login rate limiting | Done | 5 attempts / LoginRequest + throttle on OTP |
| Activity logging | Done | `activity_logs` + login/logout/failed + sales create |
| Session timeout | Done | `SESSION_LIFETIME=120` (minutes) |

## 3. Availability

| Requirement | Status | Notes |
|---|---|---|
| 99% availability | Ops | Hosting SLA |
| Daily DB backup | Done | `php artisan app:backup-database` scheduled 02:00 |
| Recovery | Ops | Restore from `storage/app/backups/*.sql` |
| Error handling | Done | Validation + report() + user-facing messages |

## 4. Reliability

| Requirement | Status | Notes |
|---|---|---|
| Financial integrity | Done | Sales/payments/investments wrap `DB::transaction` |
| DB transactions | Done | Core money/stock paths |
| No duplicate barcodes | Done | Unique `product_barcodes.barcode` |
| Atomic sale/purchase | Done | Sale + stock + payments in one transaction; stock hard-fails if insufficient |

## 5. Scalability

| Requirement | Status | Notes |
|---|---|---|
| More branches/users/products/customers | Done | Multi-branch schema + RBAC |
| New modules | Done | Module-based access in `User::ROLE_MODULES` |

## 6. Usability

| Requirement | Status | Notes |
|---|---|---|
| Modern UI / responsive | Done | Tailwind + Inertia layouts |
| Clear validation errors | Done | Inertia form errors |

## 7. Maintainability

| Requirement | Status | Notes |
|---|---|---|
| MVC / PSR / Git | Done | Laravel structure + GitHub remote |
| Service layer | Done | `SaleService`, `PurchaseService`, ledger/notifier/ActivityLogger (see ARCHITECTURE.md) |
| Repository pattern | Planned | Not used; Eloquent models are sufficient for now |
| Docs | Done | `docs/PROJECT.md`, this file |

## 8. Compatibility

| Requirement | Status | Notes |
|---|---|---|
| Modern browsers | Done | React 19 SPA via Inertia |
| Laravel 12 / PHP 8.2+ | Done | |

## 9. Database

| Requirement | Status | Notes |
|---|---|---|
| MySQL + FKs + soft delete | Done | SoftDeletes / trash flags |
| Indexing | Done | Core indexes + NFR performance migration |
| Audit trail | Partial | Stock transactions + activity_logs (sales, payments, receive stock, customers) + domain ledgers |
| Automatic backup | Done | Scheduler command (requires `php artisan schedule:run` / cron) |

## 10. Audit & tracking

| Requirement | Status | Notes |
|---|---|---|
| Purchases/sales/payments/stock/customer tx | Done | Domain tables |
| User activity | Done | `activity_logs` |

## 11. Inventory

| Requirement | Status | Notes |
|---|---|---|
| Auto stock update | Done | On sale/receive/transfer |
| No negative stock | Done | `Inventory::decrease` throws if insufficient + row lock |
| Low-stock alerts | Done | `warnIfLow` + notifications + low-stock page |
| Stock history | Done | `inventory_transactions` |

## 12. Financial

| Requirement | Status | Notes |
|---|---|---|
| Multi-currency / daily rates | Done | Exchange rate history |
| Sale & investor profit | Done | Batch profit allocation |
| Customer credit/due | Done | Customer due + payments |

## 13. Printing

| Requirement | Status | Notes |
|---|---|---|
| Invoice / receipt / reprint | Done | Receipt pages + thermal-oriented CSS |
| Thermal printer | Partial | CSS sized for 72–120mm; hardware depends on client OS |

## 14. Logging & monitoring

| Requirement | Status | Notes |
|---|---|---|
| Error / activity / login history | Done | Laravel log + `activity_logs` |
| Sensitive financial ops | Partial | Sales create logged; extend to payments/withdrawals as needed |
| Server monitoring | Ops | Hosting (htop, Netdata, etc.) |

## 15. Deployment

| Requirement | Status | Notes |
|---|---|---|
| Linux VPS, Nginx/Apache, SSL, .env | Ops | Documented targets |
| Queue worker | Done | `QUEUE_CONNECTION=database` + `queue:listen` |
| Cron / scheduler | Done | Backup at 02:00 — enable: `* * * * * php artisan schedule:run` |

---

## Essential commands

```bash
# Apply NFR schema pieces
php artisan migrate

# Manual backup
php artisan app:backup-database

# Enable scheduler (production crontab)
* * * * * cd /path/to/perfume-pos && php artisan schedule:run >> /dev/null 2>&1

# Fast local stack
composer run dev:fast
```
