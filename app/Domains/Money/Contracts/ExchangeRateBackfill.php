<?php

namespace App\Domains\Money\Contracts;

interface ExchangeRateBackfill
{
    /** @return array<int, int> */
    public function currencyIdsMissingRates(): array;

    /**
     * @param  array<int, array{id: int, exchange_rate: int|float|string}>  $currencies
     */
    public function apply(int $companyId, array $currencies): bool;
}
