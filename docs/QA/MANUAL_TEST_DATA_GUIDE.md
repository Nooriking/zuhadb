# Manual Test Data Guide — ZUHA PERFUME QA

Use this after seeding **`zuha_testing` only**.

```bash
php artisan qa:seed --database=zuha_testing
```

Point the running app at `zuha_testing` (do not use live `zuha`).

---

## 1. Login

| Who | Username | Password | Expect |
|---|---|---|---|
| Admin | `qa_admin` | `QaAdmin@123` | Full POS access |
| Super admin | `superadmin` | `Super@123` | Full access |
| Cashier | `qa_cashier` | `QaCashier@123` | Sales |
| Stock keeper | `qa_stock` | `QaStock@123` | Purchases / stock |
| Investor (active) | `AnsariUAE` | `Investor@123` | Investor dashboard |
| Investor (blocked) | `new` | `Investor@123` | Login / access blocked |

Branches: **QA Main Shop** (`QA-MAIN`), **QA Herat Shop** (`QA-HERAT`).

---

## 2. End-to-end manual checklist

Walk this chain; every step should show data from the **same** seeded transactions.

### A. Investors

1. Open Investors — confirm **Mustafa Ansari** active, **King Noori** blocked.
2. Open Mustafa — capital history markers `QA-CAPITAL-ANSARI-*`, wallet capital/profit non-zero from services.
3. Open King — status blocked; capital exists; withdrawal `QA-WD-BLOCKED-DENIED` rejected.

### B. Products & ownership

1. Catalog shows reference perfumes (SWY Absolutely, Most Wanted, Al Fareed, …).
2. Ownership appears on **purchases/batches**, not as a fake catalog-only owner:
   - SWY → store purchase `QA-PO-STORE-SWY`
   - Al Fareed → Mustafa `QA-PO-ANSARI-AF`
   - Kalemat → two batches (Ansari + Partner)
   - Sultani → store + investor mixed

### C. Purchases & inventory

1. Purchase list shows `QA-PO-*` invoices with suppliers **Kabul Perfume Wholesale** / **Dubai Fragrance Trading**.
2. Inventory:
   - Normal: SWY remaining after 1 sold
   - Low: Joop qty 2
   - Critical: Kenzo qty 1
   - Zero: Rasasi Blue Homme (never purchased)
   - Herat: Fahrenheit on second branch
3. Batch detail: original currency, FX rates, remaining qty, investor_id where applicable.

### D. Sales & FIFO

1. Open `QA-SALE-FIFO-CDN` — qty 8 Club de Nuit; COGS should reflect batch1 (2,500) then batch2 (2,650).
2. Open `QA-SALE-CASH-SWY` — investor profit **0**, store profit **1,050**.
3. Open `QA-SALE-INV-ANSARI-AF` — investor profit **1,000**, store **531**.
4. Open `QA-SALE-MULTI-INV` — Kalemat spans both investors’ batches.
5. Open `QA-SALE-PARTIAL` — paid 4,500, due 3,000 (includes follow-up `QA-PAY-FOLLOWUP-PARTIAL`).
6. Open `QA-SALE-CREDIT` — unpaid; customer **QA Credit Customer** has due.
7. Herat sale `QA-SALE-HERAT` on second branch.

### E. Payments & customers

| Customer | Phone | Expect |
|---|---|---|
| QA Cash Customer | `0791000001` | Multiple paid invoices |
| QA Credit Customer | `0791000002` | Outstanding credit sale |
| QA Partial Pay Customer | `0791000003` | Partial + mixed sales; follow-up payment |

### F. Investor profits & withdrawals

1. Investor profit entries for Mustafa reference real `sale_id`s (Al Fareed, Kalemat, etc.).
2. Partial withdrawal `QA-WD-PROFIT-PARTIAL` approved/paid.
3. Dashboard totals should match wallet + profit entries (no fabricated padding).

### G. Returns

1. `QA-RETURN-PARTIAL` — Most Wanted return of 1; stock restored; profit reversed.
2. `QA-RETURN-FULL` — Billie full return; inventory restored.

### H. Other modules

- Expenses: `QA-EXP-RENT-001`, `QA-EXP-UTIL-001`
- Notification: “QA Seed Complete”
- Currencies: AFN base, USD/AED rates with history

---

## 3. Suggested live smoke (create new records)

After reviewing seeded data, create **one** new chain as admin:

1. Deposit small capital for Mustafa (USD or AFN).
2. Receive stock for an existing variant funded by that investment.
3. Sell 1 unit — confirm FIFO batch, investor profit entry, store profit split.
4. Take a customer payment if due remains.
5. Process a partial return — confirm restock + profit reversal.

This proves the same services the seeder used still work interactively.

---

## 4. What not to expect

- Sale **cancel** that restores stock — not implemented.
- **Purchase return** module — not implemented.
- **Pending** purchases waiting to receive — receive is immediate.
- Categories — brands only.

---

## 5. Re-seed safely

```bash
# Always force the QA database name:
php artisan qa:seed --database=zuha_testing
```

Idempotent: re-run will not duplicate `QA-*` purchases/sales.  
Never run against `zuha`. Never `migrate:fresh` on production.
