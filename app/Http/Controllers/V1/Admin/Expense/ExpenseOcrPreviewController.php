<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseOcrRequest;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Expense;
use App\Services\Ocr\ExpenseOcrResult;
use App\Services\Ocr\ExpenseOcrServiceInterface;
use App\Services\Ocr\OcrFieldResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class ExpenseOcrPreviewController extends Controller
{
    public function __invoke(
        ExpenseOcrRequest $request,
        ExpenseOcrServiceInterface $expenseOcrService,
        OcrFieldResolver $ocrFieldResolver
    ): JsonResponse {
        $expense = null;

        if ($request->filled('expense_id')) {
            $expense = Expense::findOrFail($request->integer('expense_id'));
            $this->authorize('update', $expense);
        } else {
            $this->authorize('create', Expense::class);
        }

        $companyId = $expense?->company_id ?? $request->header('company');
        $ocrSettings = $this->getOcrSettings((int) $companyId);
        $requiredFields = $this->resolveRequiredFields((int) $companyId, $ocrFieldResolver);

        if ($ocrSettings['enabled'] !== 'YES') {
            return response()->json([
                'status' => 'disabled',
                'mapped_fields' => [],
                'flagged_fields' => [],
                'unmapped_fields' => [],
                'message' => 'OCR autofill is disabled for this company.',
            ]);
        }

        if ($requiredFields === null) {
            return response()->json([
                'status' => 'failed',
                'mapped_fields' => [],
                'flagged_fields' => [],
                'unmapped_fields' => [],
                'message' => 'OCR required field configuration is invalid. Please review company OCR settings.',
            ]);
        }

        $ocrResult = $expenseOcrService->extract($request->file('receipt'), [
            'country_code' => $ocrSettings['country_code'],
            'required_fields' => $requiredFields,
            'openrouter_enabled' => $ocrSettings['openrouter_enabled'] === 'YES',
            'auto_generate_templates' => $ocrSettings['auto_generate_templates_enabled'] === 'YES',
        ]);

        if ($ocrResult->status === 'failed' && ! $ocrResult->mappedFields && ! $ocrResult->unmappedFields) {
            return response()->json($ocrResult->toArray());
        }

        return response()->json(
            $this->transformOcrResult($ocrResult, $ocrSettings['confidence_threshold'])->toArray()
        );
    }

    /**
     * @return array{
     *     enabled: string,
     *     confidence_threshold: float,
     *     country_code: string,
     *     openrouter_enabled: string,
     *     auto_generate_templates_enabled: string
     * }
     */
    private function getOcrSettings(int $companyId): array
    {
        $threshold = (float) (CompanySetting::getSetting('ocr_confidence_threshold', $companyId) ?? 0.85);

        return [
            'enabled' => (string) (CompanySetting::getSetting('ocr_expense_enabled', $companyId) ?? 'NO'),
            'confidence_threshold' => max(0, min(1, $threshold)),
            'country_code' => strtoupper((string) (CompanySetting::getSetting('ocr_country_code', $companyId) ?? 'NL')),
            'openrouter_enabled' => (string) (CompanySetting::getSetting('ocr_openrouter_enabled', $companyId) ?? 'NO'),
            'auto_generate_templates_enabled' => (string) (CompanySetting::getSetting('ocr_auto_generate_templates_enabled', $companyId) ?? 'NO'),
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveRequiredFields(int $companyId, OcrFieldResolver $ocrFieldResolver): ?array
    {
        try {
            return $ocrFieldResolver->resolveForCompany($companyId);
        } catch (InvalidArgumentException $exception) {
            return null;
        }
    }

    private function transformOcrResult(ExpenseOcrResult $ocrResult, float $confidenceThreshold): ExpenseOcrResult
    {
        $mappedFields = [];
        $flaggedFields = [];
        $unmappedFields = $ocrResult->unmappedFields;

        $fieldMap = [
            'invoice_date' => 'expense_date',
            'invoice_number' => 'expense_number',
            'total_amount' => 'amount',
            'currency_code' => 'currency_id',
        ];

        foreach ($fieldMap as $sourceField => $targetField) {
            $fieldPayload = $ocrResult->mappedFields[$sourceField] ?? null;

            if (! is_array($fieldPayload)) {
                continue;
            }

            $rawValue = $fieldPayload['value'] ?? null;

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $normalizedField = $this->normalizeFieldValue($sourceField, $rawValue);

            if ($normalizedField['value'] === null) {
                $unmappedFields[$sourceField] = [
                    'value' => $rawValue,
                    'confidence' => $this->normalizeConfidence($fieldPayload['confidence'] ?? null),
                    'reason' => $normalizedField['reason'],
                ];

                continue;
            }

            $confidence = $this->normalizeConfidence($fieldPayload['confidence'] ?? null);

            if ($confidence < $confidenceThreshold) {
                $flaggedFields[$targetField] = [
                    'suggested_value' => $normalizedField['value'],
                    'confidence' => $confidence,
                    'reason' => 'Confidence below threshold.',
                ];

                continue;
            }

            $mappedFields[$targetField] = $normalizedField['value'];
        }

        foreach (Arr::except($ocrResult->mappedFields, array_keys($fieldMap)) as $field => $fieldPayload) {
            if (! is_array($fieldPayload)) {
                continue;
            }

            $unmappedFields[$field] = [
                'value' => $fieldPayload['value'] ?? null,
                'confidence' => $this->normalizeConfidence($fieldPayload['confidence'] ?? null),
                'reason' => 'Field is not mapped to the expense form.',
            ];
        }

        return new ExpenseOcrResult(
            status: $this->determineStatus($mappedFields, $flaggedFields, $unmappedFields, $ocrResult->status),
            mappedFields: $mappedFields,
            flaggedFields: $flaggedFields,
            unmappedFields: $unmappedFields,
            message: $this->determineMessage($mappedFields, $flaggedFields, $unmappedFields, $ocrResult->message),
        );
    }

    /**
     * @return array{value: int|string|null, reason: string|null}
     */
    private function normalizeFieldValue(string $sourceField, mixed $value): array
    {
        return match ($sourceField) {
            'invoice_date' => $this->normalizeDateValue($value),
            'invoice_number' => $this->normalizeStringValue($value),
            'total_amount' => $this->normalizeAmountValue($value),
            'currency_code' => $this->normalizeCurrencyValue($value),
            default => [
                'value' => null,
                'reason' => 'Field is not mapped to the expense form.',
            ],
        };
    }

    /**
     * @return array{value: string|null, reason: string|null}
     */
    private function normalizeDateValue(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [
                'value' => null,
                'reason' => 'Invoice date is missing.',
            ];
        }

        $dateFormats = [
            'Y-m-d',
            'd-m-Y',
            'd/m/Y',
            'Y/m/d',
            'd.m.Y',
        ];

        foreach ($dateFormats as $dateFormat) {
            try {
                return [
                    'value' => Carbon::createFromFormat($dateFormat, trim($value))->format('Y-m-d'),
                    'reason' => null,
                ];
            } catch (\Throwable $exception) {
            }
        }

        try {
            return [
                'value' => Carbon::parse($value)->format('Y-m-d'),
                'reason' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'value' => null,
                'reason' => 'Invoice date could not be normalized.',
            ];
        }
    }

    /**
     * @return array{value: string|null, reason: string|null}
     */
    private function normalizeStringValue(mixed $value): array
    {
        if (! is_scalar($value)) {
            return [
                'value' => null,
                'reason' => 'Invoice number is missing.',
            ];
        }

        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            return [
                'value' => null,
                'reason' => 'Invoice number is missing.',
            ];
        }

        return [
            'value' => $normalizedValue,
            'reason' => null,
        ];
    }

    /**
     * @return array{value: int|null, reason: string|null}
     */
    private function normalizeAmountValue(mixed $value): array
    {
        if (is_numeric($value)) {
            return [
                'value' => (int) round(((float) $value) * 100),
                'reason' => null,
            ];
        }

        if (! is_string($value)) {
            return [
                'value' => null,
                'reason' => 'Total amount could not be normalized.',
            ];
        }

        $normalizedValue = preg_replace('/[^0-9,\.-]/', '', $value);

        if ($normalizedValue === null || $normalizedValue === '') {
            return [
                'value' => null,
                'reason' => 'Total amount could not be normalized.',
            ];
        }

        if (str_contains($normalizedValue, ',') && str_contains($normalizedValue, '.')) {
            $normalizedValue = str_replace('.', '', $normalizedValue);
            $normalizedValue = str_replace(',', '.', $normalizedValue);
        } elseif (str_contains($normalizedValue, ',')) {
            $normalizedValue = str_replace(',', '.', $normalizedValue);
        }

        if (! is_numeric($normalizedValue)) {
            return [
                'value' => null,
                'reason' => 'Total amount could not be normalized.',
            ];
        }

        return [
            'value' => (int) round(((float) $normalizedValue) * 100),
            'reason' => null,
        ];
    }

    /**
     * @return array{value: int|null, reason: string|null}
     */
    private function normalizeCurrencyValue(mixed $value): array
    {
        if (! is_scalar($value)) {
            return [
                'value' => null,
                'reason' => 'Currency code is missing.',
            ];
        }

        $currencyCode = strtoupper(trim((string) $value));

        if ($currencyCode === '') {
            return [
                'value' => null,
                'reason' => 'Currency code is missing.',
            ];
        }

        $currency = Currency::where('code', $currencyCode)->first();

        if (! $currency) {
            return [
                'value' => null,
                'reason' => 'Currency code is not supported.',
            ];
        }

        return [
            'value' => $currency->id,
            'reason' => null,
        ];
    }

    private function normalizeConfidence(mixed $confidence): float
    {
        if (! is_numeric($confidence)) {
            return 0;
        }

        return (float) $confidence;
    }

    /**
     * @param  array<string, int|string>  $mappedFields
     * @param  array<string, array<string, mixed>>  $flaggedFields
     * @param  array<string, array<string, mixed>>  $unmappedFields
     */
    private function determineStatus(
        array $mappedFields,
        array $flaggedFields,
        array $unmappedFields,
        string $fallbackStatus
    ): string {
        if (! $mappedFields && ! $flaggedFields && $fallbackStatus === 'failed') {
            return 'failed';
        }

        if ($mappedFields && ! $flaggedFields && ! $unmappedFields) {
            return 'success';
        }

        if ($flaggedFields && ! $mappedFields) {
            return 'needs_review';
        }

        if ($mappedFields || $flaggedFields || $unmappedFields) {
            return 'partial';
        }

        return $fallbackStatus ?: 'failed';
    }

    /**
     * @param  array<string, int|string>  $mappedFields
     * @param  array<string, array<string, mixed>>  $flaggedFields
     * @param  array<string, array<string, mixed>>  $unmappedFields
     */
    private function determineMessage(
        array $mappedFields,
        array $flaggedFields,
        array $unmappedFields,
        string $fallbackMessage
    ): string {
        if (! $mappedFields && ! $flaggedFields && $fallbackMessage) {
            return $fallbackMessage;
        }

        if ($mappedFields && ! $flaggedFields && ! $unmappedFields) {
            return 'Expense details extracted successfully.';
        }

        if ($mappedFields || $flaggedFields || $unmappedFields) {
            return 'Some expense details need review before submission.';
        }

        return $fallbackMessage ?: 'We could not extract invoice details. Please fill in the expense manually.';
    }
}
