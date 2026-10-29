<?php

namespace App\Domains\Sales\Contracts;

use App\Domains\Sales\Models\Estimate;

interface EstimatePdfDataProvider
{
    public function getPdfData(Estimate $estimate): mixed;
}
