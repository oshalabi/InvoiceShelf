<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\Company;

interface CompanyDataPurger
{
    public function purge(Company $company): void;
}
