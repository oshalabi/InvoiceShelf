<?php

namespace App\Domains\Money\Http\Controllers;

use App\Domains\Money\Application\CurrencyService;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reference listing of every currency the installation knows about. Currencies
 * are global data, so nothing here is company scoped; the service decides the
 * ordering (the widely used codes lead, everything else follows by name).
 */
class CurrenciesController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencyService,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CurrencyResource::collection(
            $this->currencyService->getAllWithCommonFirst(),
        );
    }
}
