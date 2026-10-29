<?php

namespace App\Adapters\Catalog;

use App\Domains\Catalog\Contracts\ItemTaxManager;
use App\Domains\Catalog\Models\Item;

class TaxationItemTaxManager implements ItemTaxManager
{
    public function attach(Item $item, array $taxes, int $companyId): void
    {
        if ($taxes === []) {
            return;
        }

        $item->update(['tax_per_item' => true]);

        foreach ($taxes as $tax) {
            $item->taxes()->create([
                ...$tax,
                'company_id' => $companyId,
            ]);
        }
    }

    public function replace(Item $item, array $taxes, int $companyId): void
    {
        $item->taxes()->delete();

        $this->attach($item, $taxes, $companyId);
    }
}
