<?php

namespace App\Domains\Contacts\Contracts;

use App\Domains\Contacts\Models\Customer;

interface CustomerAvatarManager
{
    public function clear(Customer $customer): void;

    public function replace(Customer $customer, string $path, string $fileName): void;
}
