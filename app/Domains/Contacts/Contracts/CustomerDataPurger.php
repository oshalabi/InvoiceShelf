<?php

namespace App\Domains\Contacts\Contracts;

use App\Domains\Contacts\Models\Customer;

interface CustomerDataPurger
{
    public function purge(Customer $customer): void;
}
