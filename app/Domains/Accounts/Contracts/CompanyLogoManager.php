<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\Company;

interface CompanyLogoManager
{
    public function clear(Company $company): void;

    public function replaceBase64(Company $company, string $contents, string $fileName): void;
}
