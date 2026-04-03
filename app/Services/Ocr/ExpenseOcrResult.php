<?php

namespace App\Services\Ocr;

class ExpenseOcrResult
{
    /**
     * @param  array<string, mixed>  $mappedFields
     * @param  array<string, array<string, mixed>>  $flaggedFields
     * @param  array<string, array<string, mixed>>  $unmappedFields
     */
    public function __construct(
        public string $status,
        public array $mappedFields = [],
        public array $flaggedFields = [],
        public array $unmappedFields = [],
        public string $message = '',
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     mapped_fields: array<string, mixed>,
     *     flagged_fields: array<string, array<string, mixed>>,
     *     unmapped_fields: array<string, array<string, mixed>>,
     *     message: string
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'mapped_fields' => $this->mappedFields,
            'flagged_fields' => $this->flaggedFields,
            'unmapped_fields' => $this->unmappedFields,
            'message' => $this->message,
        ];
    }
}
