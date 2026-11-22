# Investor → Product → Purchase → Sale Seeding Report

**Database:** `zuha_testing`  
**Executed:** 2026-11-16 via `php artisan qa:seed --database=zuha_testing`  
**Reconciliation after seed:** 7 PASS / 0 FAIL

---

## 1. Investors (Priority 1)

| Investor | Username | Status | Capital path |
|---|---|---|---|
| King Noori | `new` | **blocked** (after capital deposit) | `InvestmentCapitalService::deposit` — USD 5,000 + AFN 200,000 |
| Mustafa Ansari | `AnsariUAE` | **active** | USD 8,000 + AED 15,000 + AFN 350,000 |
| QA Partner Investor | `qa_partner` | active | AFN 150,000 |

- Identity: `Investor` factory / users row with `role=investor`
- Wallets: `getOrCreateWallet()` then capital via service (balances not fabricated)
- Markers: `QA-CAPITAL-KING-*`, `QA-CAPITAL-ANSARI-*`, `QA-CAPITAL-PARTNER-*`

---

## 2. Product catalog (Priority 2)

22 variants across reference brands (D&G, RASASI, EMPORIO ARMANI, AZZARO, Arabian oud, Givenchy, Chanel, DIOR, HERMES, KENZO, JOOP, MANCERA, VERSACE, MONTBLANC, THOMAS KOSMALA, ARMAF, Billie Eilish).

### Known reference prices

| Product | Purchase AFN | Selling AFN | SKU |
|---|---:|---:|---|
| Stronger With YOU Absolutely | 5,450 | 6,500 | `QA-SWY-ABS-100` |
| The Most Wanted Parfum | 3,616 | 6,000 | `QA-AZZ-MW-100` |
| Al Fareed | 8,469 | 10,000 | `QA-AO-AF-100` |

Other catalog prices are tagged **QA-SYNTHETIC** in product description.

**Ownership is not on catalog products** — assigned at purchase/batch receive time.

---

## 3. Purchases & inventory (Priority 3–4)

19 purchases via `PurchaseService::receiveStock` (0 inventory invented).

| Marker | Ownership | Currency | Notes |
|---|---|---|---|
| `QA-PO-STORE-SWY` | Store | AFN | Fully paid |
| `QA-PO-ANSARI-MW` | Mustafa | AFN | Investor |
| `QA-PO-ANSARI-AF` | Mustafa | AFN | Outstanding supplier (`paid_now=0`) |
| `QA-PO-ANSARI-KAL` / `QA-PO-PARTNER-KAL` | Multi-investor | AFN | Different unit costs |
| `QA-PO-STORE-SULT` / `QA-PO-ANSARI-SULT` | Mixed store+investor | AFN | Partial payment on investor leg |
| `QA-PO-USD-BDC` | Store | USD | FX snapshot |
| `QA-PO-AED-HAWAS` | Mustafa | AED | FX snapshot |
| `QA-PO-KING-EROS` | King (blocked) | AFN | Historical capital still funds stock |
| `QA-PO-HERAT-FAH` | Store | AFN | Second branch |
| `QA-PO-LOW-JOOP` / `QA-PO-CRIT-KENZO` | Store | AFN | Low / critical stock |
| `QA-PO-CDN-B1` / `QA-PO-CDN-B2` | Store | AFN | Multi-batch FIFO |
| Others | Investor/store | AFN | Gentleman, D&G, Explorer, Billie |

**Zero stock:** `rasasi_blue` catalogued but never purchased.

Each purchase row has a matching `product_batches` row with FX snapshot + ownership.

---

## 4. Sales & FIFO (Priority 5)

13 completed sales via `SaleService::createSale`.

| Marker | Scenario | Gross profit | Investor profit | Store profit |
|---|---|---:|---:|---:|
| `QA-SALE-CASH-SWY` | Store cash | 1,050 | **0** | 1,050 |
| `QA-SALE-MULTI-PROD` | Multi-product | 3,884 | 800 | 3,084 |
| `QA-SALE-FIFO-CDN` | FIFO across 2 batches (qty 8) | 10,100 | 0 | 10,100 |
| `QA-SALE-INV-ANSARI-AF` | Investor Al Fareed | 1,531 | 1,000 | 531 |
| `QA-SALE-MIXED-OWN` | Store + investor lines | 5,900 | 550 | 5,350 |
| `QA-SALE-MULTI-INV` | Kalemat spans 2 investors | 15,800 | 4,900 | 10,900 |
| `QA-SALE-PARTIAL` | Partial + follow-up pay | 2,000 | 700 | 1,300 |
| `QA-SALE-CREDIT` | Credit | 1,416 | 400 | 1,016 |
| `QA-SALE-DISCOUNT` | Discount 200 | 1,500 | 700 | 800 |
| `QA-SALE-HERAT` | Branch 2 | 2,000 | 0 | 2,000 |
| `QA-SALE-KING-EROS` | Blocked investor ownership | 1,700 | 700 | 1,000 |
| `QA-SALE-RETURN-SRC` | Full return source | (adjusted after return) | — | — |
| `QA-SALE-PARTIAL-RET-SRC` | Partial return source | (adjusted after return) | — | — |

**Identity verified:** `total_profit_afn ≈ total_investor_profit_afn + store_profit_afn` on non-returned sales.

**Insufficient stock:** attempt on `rasasi_blue` correctly blocked (`ValidationException`).

**Unauthorized sale:** policy/HTTP only (documented; not bypassed via service).

**Follow-up payment:** `QA-PAY-FOLLOWUP-PARTIAL` via `SaleService::addPayment` (PARTIAL paid 4,500 / due 3,000).

---

## 5. Returns & withdrawals

| Marker | Type |
|---|---|
| `QA-RETURN-PARTIAL` | 1 of 2 Most Wanted — restock + profit reverse |
| `QA-RETURN-FULL` | Full Billie return |
| `QA-WD-PROFIT-PARTIAL` | Mustafa profit withdrawal (approve → pay) |
| `QA-WD-BLOCKED-DENIED` | King rejected withdrawal (blocked) |

---

## 6. Traceability chain (example)

**Mustafa → Al Fareed → sale → profit**

1. Capital `QA-CAPITAL-ANSARI-*` → investment cash balance  
2. Purchase `QA-PO-ANSARI-AF` → batch with `investor_id=Mustafa`, `profit_per_unit=1000`  
3. Sale `QA-SALE-INV-ANSARI-AF` → FIFO consumes that batch  
4. `InvestorProfitEntry` linked to `sale_id` + investor  
5. Wallet profit balance updated by real sale path (not inserted totals)

**Store → SWY → sale → store-only profit**

1. `QA-PO-STORE-SWY` → no investor on batch  
2. `QA-SALE-CASH-SWY` → `total_investor_profit_afn = 0`, store keeps gross
