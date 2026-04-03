<?php

namespace App\Services\Ocr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class HttpExpenseOcrService implements ExpenseOcrServiceInterface
{
    /**
     * @param  array{country_code?: string}  $options
     */
    public function extract(UploadedFile $file, array $options = []): ExpenseOcrResult
    {
        $baseUrl = config('services.ocr.base_url');

        if (! $baseUrl) {
            return new ExpenseOcrResult(
                status: 'failed',
                message: 'OCR service unavailable. Please try again later or enter the expense manually.',
            );
        }

        $timeout = (int) config('services.ocr.timeout', 5);
        $countryCode = strtoupper((string) ($options['country_code'] ?? 'NL'));
        $apiKey = config('services.ocr.api_key');

        try {
            $request = Http::acceptJson()
                ->timeout($timeout)
                ->retry(2, 200);

            if ($apiKey) {
                $request = $request->withToken($apiKey);
            }

            $response = $request
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post($baseUrl, [
                    'country_code' => $countryCode,
                ]);
        } catch (ConnectionException $exception) {
            return new ExpenseOcrResult(
                status: 'failed',
                message: 'OCR service unavailable. Please try again later or enter the expense manually.',
            );
        }

        if (! $response->successful()) {
            return new ExpenseOcrResult(
                status: 'failed',
                message: 'OCR service unavailable. Please try again later or enter the expense manually.',
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return new ExpenseOcrResult(
                status: 'failed',
                message: 'We could not extract invoice details. Please fill in the expense manually.',
            );
        }

        return new ExpenseOcrResult(
            status: (string) ($payload['status'] ?? 'success'),
            mappedFields: $this->extractFields($payload),
            flaggedFields: [],
            unmappedFields: $this->extractUnmappedFields($payload),
            message: (string) ($payload['message'] ?? 'Expense details extracted successfully.'),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractFields(array $payload): array
    {
        $fields = $payload['fields'] ?? $payload['data']['fields'] ?? [];

        if (! is_array($fields)) {
            $fields = [];
        }

        if (! $fields) {
            foreach (['invoice_date', 'invoice_number', 'total_amount', 'currency_code'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $fields[$key] = $payload[$key];
                }
            }
        }

        return $this->normalizeFieldCollection($fields);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractUnmappedFields(array $payload): array
    {
        $unmappedFields = $payload['unmapped_fields'] ?? $payload['data']['unmapped_fields'] ?? [];

        if (! is_array($unmappedFields)) {
            return [];
        }

        return $this->normalizeFieldCollection($unmappedFields);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function normalizeFieldCollection(array $fields): array
    {
        $normalizedFields = [];

        foreach ($fields as $key => $field) {
            $normalizedField = $this->normalizeField($field);

            if ($normalizedField !== null) {
                $normalizedFields[$key] = $normalizedField;
            }
        }

        return $normalizedFields;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeField(mixed $field): ?array
    {
        if (is_array($field)) {
            return [
                'value' => $field['value'] ?? $field['text'] ?? $field['content'] ?? null,
                'confidence' => (float) ($field['confidence'] ?? 1),
            ];
        }

        if (is_scalar($field) || $field === null) {
            return [
                'value' => $field,
                'confidence' => 1.0,
            ];
        }

        return null;
    }
}
