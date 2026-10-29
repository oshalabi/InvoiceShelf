<?php

namespace App\Domains\Money\Application;

use App\Domains\Money\ExchangeRates\ExchangeRateDriverFactory;
use App\Domains\Money\Models\ExchangeRateProvider;
use Illuminate\Database\Eloquent\Collection;

class ExchangeRateProviderService
{
    /** @param array<string, mixed> $payload */
    public function create(array $payload): ExchangeRateProvider
    {
        return ExchangeRateProvider::create($payload);
    }

    /** @param array<string, mixed> $payload */
    public function update(ExchangeRateProvider $provider, array $payload): ExchangeRateProvider
    {
        $provider->update($payload);

        return $provider;
    }

    /**
     * @param  array<int, string>  $currencies
     * @return Collection<int, ExchangeRateProvider>
     */
    public function checkActiveCurrencies(array $currencies): Collection
    {
        if (empty($currencies)) {
            return new Collection;
        }

        $query = ExchangeRateProvider::where('active', true);

        foreach ($currencies as $currency) {
            $query->orWhere(function ($q) use ($currency) {
                $q->where('active', true)
                    ->whereJsonContains('currencies', $currency);
            });
        }

        return $query->get();
    }

    /**
     * @param  array<int, string>  $currencies
     * @return Collection<int, ExchangeRateProvider>
     */
    public function checkUpdateActiveCurrencies(ExchangeRateProvider $provider, array $currencies): Collection
    {
        if (empty($currencies)) {
            return new Collection;
        }

        $query = ExchangeRateProvider::where('id', '<>', $provider->id)
            ->where('active', true);

        $query->where(function ($q) use ($currencies) {
            foreach ($currencies as $currency) {
                $q->orWhereJsonContains('currencies', $currency);
            }
        });

        return $query->get();
    }

    /** @param array<string, mixed> $configuration */
    public function validateProvider(array $configuration): array
    {
        return ExchangeRateDriverFactory::make(
            $configuration['driver'],
            $configuration['key'],
            $configuration['driver_config'] ?? [],
        )->validateConnection();
    }

    public function getExchangeRate(
        string $driver,
        string $apiKey,
        array $driverConfig,
        string $baseCurrency,
        string $targetCurrency,
    ): array {
        return ExchangeRateDriverFactory::make($driver, $apiKey, $driverConfig)
            ->getExchangeRate($baseCurrency, $targetCurrency);
    }

    /** @return array<int, string> */
    public function getSupportedCurrencies(string $driver, string $apiKey, array $driverConfig = []): array
    {
        return ExchangeRateDriverFactory::make($driver, $apiKey, $driverConfig)
            ->getSupportedCurrencies();
    }
}
