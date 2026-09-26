# Zuha Perfume POS

This document describes the project: what it does, the rule each section follows, and how to run it. The application screens are in English.

## 1. What this system is

Zuha is a multi-branch perfume point of sale and inventory system. Sales, customers, suppliers, stock, branch transfers, investors, expenses, and receipts live in one application.

Open `http://localhost:8000`. The first screen is login. After login, the dashboard for that user's role opens.

## 2. Technology

| Layer | Tool |
|---|---|
| Server | Laravel 12, PHP 8.2 |
| Interface | Inertia, React, Vite, Tailwind |
| Live database | MySQL database `zuha`, host `127.0.0.1`, port `3306`, user `root` |
| Test database | MySQL database `zuha_testing` |
| Dev processes | `composer run dev` starts the server, the queue, and Vite together |

Daily start, from the project folder:

```bash
composer run dev
```

Do not start a second `npm run dev`. The live log viewer (Pail) does not start on Windows, because the `pcntl` extension is missing. The application still runs.

Tests use `zuha_testing` only. A test run must not wipe the live `zuha` database.

## 3. Folder map

| Path | Contents |
|---|---|
| `app/Http/Controllers` | Section logic |
| `app/Models` | Tables and relationships |
| `app/Models/User.php` | Roles, modules, and permissions |
| `routes/web.php` | Main application routes |
| `routes/auth.php` | Login and password reset |
| `routes/settings.php` | Profile, password, and appearance |
| `resources/js/pages` | Screen for each section |
| `resources/js/components/row-actions.tsx` | Row action buttons |
| `resources/js/components/index-filters.tsx` | Index search and filters |
| `database/factories` | Model factories |
| `database/seeders` | Initial and sample data |
| `tests/Feature` | Capability tests |

## 4. Roles

Every user has one role. The role limits both the menu and the actions.

| Role | Main work |
|---|---|
| Super Admin | Every section. Branches, the product trash, and permanent delete of some records are limited to this role |
| Admin | Sales, stock, customers, suppliers, investors, expenses, users, settings |
| Branch Manager | The same shop work inside their own branch: sales, products, customers, suppliers, inventory, transfers, and creating cashiers, salespeople, and stock keepers for that branch |
| Cashier | Cashier dashboard, customers, sales, sale returns, inventory view |
| Salesperson | Customers, sales, sale returns, and products (view, create, update, delete) |
| Stock Keeper | Products, brands, suppliers, inventory, purchases, stock transfers |
| Accountant | Investors, investments, withdrawals, profit, expenses, reports, product view |
| Investor | The investor dashboard only |

A branch manager sees only users in their own branch and can create only Cashier, Salesperson, and Stock Keeper. A branch-scoped user is limited to their own branch on sales and inventory lists.

The Stock Keeper role exists in code. Creating that user fails on the live database until the `users.role` column includes it. Apply migration `2026_09_25_153600_add_stock_keeper_to_users_role` on `zuha` first.

## 5. Money: Afghani and US dollars

The system keeps two kinds of money separate.

- Shop invoices, purchase price, sale price, customer debt, and supplier debt are **Afghani (AFN)**.
- Investor money (capital, profit, withdrawal) is **US dollars**, stored in columns whose names end in `_afn`.
- The exchange rate is how many afghanis equal one dollar. Dollars = afghanis divided by the rate.
- On the product form, investor profit is typed in afghanis. The system converts it to dollars and stores it on the batch.
- A purchase for the shop itself has no investor profit. The batch stores zero.
- A sale price cannot be negative and cannot be below the purchase price.
- A later cheaper purchase writes the loss on the remaining stock into expenses.

## 6. Sections

### Dashboard

`/dashboard`, plus separate dashboards for the cashier, stock, accountant, branch manager, and investor. Chart series use gold and blue. Light mode is a white background. Dark mode is black and gold.

### Branches

`/branches` is Super Admin only. Activate and deactivate a branch here. Branch delete is not on the list.

### Users

`/users` is for Super Admin, Admin, and Branch Manager. Block and unblock are on this page. User trash and permanent delete are Super Admin only.

### Brands and products

`/brands` and `/products`.

A product has variants (volume and concentration), barcodes, purchase price, sale price, and stock. Product status is not on the product form. The inventory page shows quantity and total amount only.

Add stock at `/products/receive`. The barcode scan at the top looks the product up in the database. The US dollar to Afghani rate sits at the bottom of the form.

When a product's quantity becomes 1 or 2, Super Admin and Admin receive a notification titled "Low stock: 2 or fewer left". An unread notification with the same body is not created again.

### Suppliers

`/suppliers`.

Debt comes from `supplier_invoices`. Opening balance is forced to 0. On the index, **Pay debts** records a payment, reduces the bills, and opens a receipt with the amount paid and the balance still left. Whole account applies the amount to the oldest bills first.

### Customers

`/customers`.

Balance, credit limit, the transaction ledger, and debt collection. On a sale, an existing customer can be edited in place and saved with Save customer. Credit sales use the Credit option: the paid amount is set to zero and a customer is required.

### Inventory and transfers

`/inventory` is stock, low stock, and stock movement. Adjust is available to Admin, Branch Manager, and Stock Keeper.

`/stock-transfers` moves stock between branches. A draft can be completed or cancelled. After completion, edit and delete are closed.

### Sales

`/sales`.

Search matches invoice number, receipt number, customer name, and customer phone, and it runs while typing. Each row has view, edit, and print receipt.

Sale returns are at `/sale-returns`. The product amount is deducted from the sale and from the customer credit. If a return line has an item expense, only that typed amount is written to expenses. An expense of 0 creates no expense.

### Investors

`/investors`, `/investments`, `/investor-withdrawals`, `/investor-profit-entries`, `/investor-transactions`.

The wallet's capital and profit are in US dollars. Creating a withdrawal does not debit the wallet until it is marked paid. Settle zeros capital and profit and opens a receipt. Unsold stock is not removed by settle.

### Expenses, receipts, and rates

`/expenses` is shop expenses in afghanis.

`/receipts` is collection receipts.

`/exchange-rate-history` is the US dollar to Afghani rate history. The shop name, phone, and address from application settings appear on bills and in the sidebar. Until settings are saved once, bills use the fallback values.

### Password reset

Forgot password emails a 6-digit code. The code is valid for 10 minutes. Until mail is configured with SMTP, the code is written to the Laravel log and is not delivered to Gmail. `MAIL_MAILER` defaults to `log`.

## 7. Row actions

Index rows use icons for actions.

- Three actions or fewer stay as icons side by side (view, edit, delete, print).
- More than three actions collapse into a three-dot menu, and each action is listed by name.

The rule lives in `resources/js/components/row-actions.tsx`. Suppliers, customers, investors, brands, currencies, and draft stock transfers use the menu because those rows have more than three actions.

## 8. Search and filters

Index pages use `IndexFilters`: one search row and the filters in a single card. Filters are not hidden behind a Show Filters button.

## 9. Sample data

Factories for the models in use are in `database/factories`. Load the sample with:

```bash
php artisan db:seed --class=FactorySampleSeeder --force
```

Markers you can search for:

| Section | Marker |
|---|---|
| Sale | `FACTORY-0001` |
| Customer | phone `0701112233` |
| Product | barcode `8901110000001`, name Factory Rose |
| Supplier | phone `0702223344`, bill `FACTORY-BILL-0001` |
| Investor | phone `0703334455`, investment `FACTORY-INV-0001` |
| Return | `FACTORY-RET-0001` |
| Expense | `FACTORY-EXP-0001` |
| Transfer | `FACTORY-TRF-0001` |

The live database does not currently have the `currencies` and `purchase_items` tables. Their factories exist, and the sample seeder skips those two tables.

`UserSeeder` rewrites user passwords. Do not run it again when loading sample data.

## 10. Low-stock notification

When stock increases, decreases, or is adjusted, a notification is created if the quantity is 1 or 2. The notification section is `inventory` and the link is `/inventory`.
