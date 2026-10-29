<?php

namespace App\Domains\Sales\Contracts;

use Illuminate\Database\Eloquent\Model;

interface DocumentExchangeRateRecorder
{
    public function record(Model $document): void;
}
