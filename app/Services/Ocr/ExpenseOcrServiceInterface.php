<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;

interface ExpenseOcrServiceInterface
{
    /**
     * @param  array{country_code?: string}  $options
     */
    public function extract(UploadedFile $file, array $options = []): ExpenseOcrResult;
}
