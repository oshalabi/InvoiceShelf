<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\Company;

interface CompanyDefaultsProvisioner
{
    public function provision(Company $company): void;
}
