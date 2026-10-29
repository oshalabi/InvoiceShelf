<?php

namespace App\Domains\Contacts\Contracts;

use App\Domains\Contacts\Models\Customer;

interface CustomerPortalDashboardProvider
{
    /** @return array<string, mixed> */
    public function get(Customer $customer): array;
}
