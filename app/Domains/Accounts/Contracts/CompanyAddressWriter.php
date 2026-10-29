<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\Company;

interface CompanyAddressWriter
{
    /** @param array<string, mixed> $attributes */
    public function upsert(Company $company, array $attributes): void;
}
