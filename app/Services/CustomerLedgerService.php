<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;

/**
 * Cash-only POS: customer_balances / customer_transactions tables were removed.
 * Methods are kept as no-ops so existing call sites do not break.
 */
class CustomerLedgerService
{
    public function recordSale(Sale $sale): void
    {
        // no-op (cash sales)
    }

    public function recordPayment(SalePayment $payment): void
    {
        // no-op (cash sales)
    }

    public function recordReturn(SaleReturn $return): void
    {
        // no-op (cash sales)
    }

    public function recalculate(int $customerId): void
    {
        // no-op (cash sales)
    }
}
