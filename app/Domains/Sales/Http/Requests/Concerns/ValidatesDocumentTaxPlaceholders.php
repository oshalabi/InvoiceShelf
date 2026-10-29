<?php

namespace App\Domains\Sales\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesDocumentTaxPlaceholders
{
    protected function validateDocumentTaxPlaceholders(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            foreach ($items as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $taxes = $item['taxes'] ?? [];

                if (! is_array($taxes)) {
                    continue;
                }

                foreach ($taxes as $taxIndex => $tax) {
                    if (! is_array($tax) || ! empty($tax['tax_type_id'])) {
                        continue;
                    }

                    if ((float) ($tax['amount'] ?? 0) !== 0.0) {
                        $validator->errors()->add(
                            "items.{$itemIndex}.taxes.{$taxIndex}.amount",
                            'A tax amount requires a tax type.'
                        );
                    }
                }
            }
        });
    }
}
