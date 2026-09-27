# Architecture — Zuha Perfume POS (Phase A)

Laravel 12 + Inertia React + MySQL.

## Layering

```text
HTTP (Inertia / JSON)
    → Form Request (validation)
    → Controller (authorize + orchestrate)
    → Service (business rules + DB::transaction)
    → Eloquent models
    → MySQL
```

Cross-cutting:

- **Middleware**: `auth`, `verified`, `role`, `permission`, `module`
- **Policies**: `SalePolicy`, `ProductPolicy`, `CustomerPolicy` (permission-backed)
- **Audit**: `ActivityLogger` → `activity_logs`

## Why no Repository layer (YAGNI)

Eloquent already is the data-access API. A Repository per model would duplicate query methods without a second persistence target. Services call models directly. Repositories can be added later if a second store or complex query graph appears.

## Why no domain Events / Queues yet (YAGNI)

Sale create already runs notifier + ledger + inventory inside one transaction. Splitting into `SaleCreated` listeners is a Phase B option when async PDF/email/SMS is required.

## Core services

| Service | Responsibility |
|---|---|
| `SaleService` | Create sale, stock check, FIFO, payments, profit, ledger; add payment |
| `PurchaseService` | Receive stock (batch, inventory, price history, optional supplier pay) |
| `CustomerLedgerService` | Customer sale / payment / return ledger rows |
| `InventoryService` | Stock helpers (legacy / alternate paths) |
| `ActivityLogger` | Audit trail |
| `SaleNotifier` | In-app notifications after sale |

## Sale flow

```text
StoreSaleRequest
  → SaleController::store
  → authorize(create, Sale)
  → SaleService::createSale
       ├─ assert stock (batches)
       ├─ DB::transaction
       │    ├─ Sale + SaleItems
       │    ├─ Inventory::decrease + batch reduce
       │    ├─ InvestorProfitEntry (if investor batch)
       │    ├─ SalePayments
       │    └─ CustomerLedgerService
       ├─ SaleNotifier
       └─ ActivityLogger
```

## Receive-stock flow

```text
ProductController::receiveStore
  → authorize(create, Product)
  → PurchaseService::receiveStock
       └─ DB::transaction
            ├─ update variant prices
            ├─ purchase row (products as purchase line)
            ├─ ProductBatch
            ├─ ProductPriceHistory
            ├─ Inventory::increase
            └─ ActivityLogger
```

## Configuration

Business constants live in [`config/business.php`](../config/business.php):

- `default_currency`
- `default_low_stock_threshold`
- `sale_exchange_window_days`
- `manufacturer` / `support_phone`

Shared shop payload: `AppSetting::bill()` (includes manufacturer + support from config defaults).

## Testing

Feature: `tests/Feature/PosCoreScenariosTest.php`  
Unit: `tests/Unit/SaleServiceCalculationTest.php`
