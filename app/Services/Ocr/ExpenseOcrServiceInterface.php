<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;

interface ExpenseOcrServiceInterface
{
    /**
     * @param  array{
     *     country_code?: string,
     *     required_fields?: array<int, string>,
     *     openrouter_enabled?: bool,
     *     auto_generate_templates?: bool
     * }  $options
     */
    public function extract(UploadedFile $file, array $options = []): ExpenseOcrResult;
}
