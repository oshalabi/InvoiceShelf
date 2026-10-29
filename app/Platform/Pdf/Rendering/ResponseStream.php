<?php

namespace App\Platform\Pdf\Rendering;

use Illuminate\Http\Response;

/**
 * The rendered-document contract every PDF driver returns.
 *
 * The defaults matter: callers reach for the bare `stream()` / `download()`
 * (see the report controllers and GeneratesPdf), so a driver that only
 * accepts an explicit filename would break them.
 */
interface ResponseStream
{
    public function stream(string $filename = 'document.pdf'): Response;

    public function download(string $filename = 'document.pdf'): Response;

    public function output(): string;
}
