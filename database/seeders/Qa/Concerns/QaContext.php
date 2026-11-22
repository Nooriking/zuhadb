<?php

namespace Database\Seeders\Qa\Concerns;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared handles resolved by foundation / investor seeders.
 */
final class QaContext
{
    public User $admin;

    public User $cashier;

    public User $stockKeeper;

    public Branch $mainBranch;

    public Branch $secondBranch;

    public Currency $afn;

    public Currency $usd;

    public Currency $aed;

    public Supplier $supplierAfn;

    public Supplier $supplierUsd;

    public Customer $cashCustomer;

    public Customer $creditCustomer;

    public Customer $partialCustomer;

    public Investor $kingNoori;

    public Investor $mustafaAnsari;

    public Investor $qaPartner;

    /** @var Collection<string, \App\Models\ProductVariant> */
    public Collection $variants;

    public function __construct()
    {
        $this->variants = collect();
    }
}
