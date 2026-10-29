<?php

namespace App\Adapters\Sales;

use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use Illuminate\Database\Eloquent\Model;

class MoneyDocumentExchangeRateRecorder implements DocumentExchangeRateRecorder
{
    public function record(Model $document): void
    {
        ExchangeRateLog::addExchangeRateLog($document);
    }
}
