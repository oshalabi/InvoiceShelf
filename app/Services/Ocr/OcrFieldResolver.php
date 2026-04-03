<?php

namespace App\Services\Ocr;

use App\Models\CompanySetting;
use InvalidArgumentException;

class OcrFieldResolver
{
    /**
     * @var array<int, string>
     */
    public const SUPPORTED_FIELDS = [
        'invoice_number',
        'date',
        'amount',
        'currency_code',
    ];

    /**
     * @return array<int, string>
     */
    public function resolveForCompany(int $companyId): array
    {
        $configuredFields = CompanySetting::getSetting('ocr_required_fields', $companyId);

        if (! is_string($configuredFields) || trim($configuredFields) === '') {
            $configuredFields = (string) config('services.ocr.required_fields', 'invoice_number,date,amount,currency_code');
        }

        return $this->normalize($configuredFields);
    }

    /**
     * @param  array<int, string>|string  $configuredFields
     * @return array<int, string>
     */
    public function normalize(array|string $configuredFields): array
    {
        $rawFields = is_array($configuredFields)
            ? $configuredFields
            : explode(',', $configuredFields);

        $normalizedFields = [];

        foreach ($rawFields as $rawField) {
            $field = trim((string) $rawField);

            if ($field === '') {
                continue;
            }

            if (! in_array($field, self::SUPPORTED_FIELDS, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported OCR required field: %s', $field));
            }

            if (in_array($field, $normalizedFields, true)) {
                continue;
            }

            $normalizedFields[] = $field;
        }

        if (! $normalizedFields) {
            throw new InvalidArgumentException('At least one OCR required field must be configured.');
        }

        return $normalizedFields;
    }
}
