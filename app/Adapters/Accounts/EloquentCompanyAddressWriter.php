<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\CompanyAddressWriter;
use App\Domains\Accounts\Models\Company;

class EloquentCompanyAddressWriter implements CompanyAddressWriter
{
    public function upsert(Company $company, array $attributes): void
    {
        $company->address()->updateOrCreate(
            ['company_id' => $company->id],
            $attributes,
        );
    }
}
