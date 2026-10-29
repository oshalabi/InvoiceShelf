<?php

namespace App\Domains\Catalog\Contracts;

use App\Domains\Catalog\Models\Item;

interface ItemTaxManager
{
    /** @param array<int, array<string, mixed>> $taxes */
    public function attach(Item $item, array $taxes, int $companyId): void;

    /** @param array<int, array<string, mixed>> $taxes */
    public function replace(Item $item, array $taxes, int $companyId): void;
}
