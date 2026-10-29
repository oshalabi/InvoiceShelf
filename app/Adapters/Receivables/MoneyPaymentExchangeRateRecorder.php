<?php

namespace App\Adapters\Receivables;

use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Receivables\Contracts\PaymentExchangeRateRecorder;
use App\Domains\Receivables\Models\Payment;

class MoneyPaymentExchangeRateRecorder implements PaymentExchangeRateRecorder
{
    public function record(Payment $payment): void
    {
        ExchangeRateLog::addExchangeRateLog(model: $payment);
    }
}
