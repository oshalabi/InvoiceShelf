<?php

namespace App\Platform\Pdf\Contracts;

interface PdfConfigurator
{
    public function applyGlobalConfig(): void;
}
