<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Sales\Models\Invoice;
use App\Rules\CreditNoteDeletedTogether;
use App\Rules\RelationNotExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of the bulk invoice removal endpoint: a list of ids, each of which
 * has to name a real invoice that nothing is still hanging off. Company scoping
 * is applied by the controller when it resolves the ids, not here.
 */
class DeleteInvoiceRequest extends FormRequest
{
    /**
     * The ability is checked in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An id survives validation when the invoice exists, carries no payment,
     * and takes any credit note written against it along in the same batch.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $batch = (array) $this->input('ids', []);

        return [
            'ids' => 'required',
            'ids.*' => [
                'required',
                Rule::exists('invoices', 'id'),
                new RelationNotExist(Invoice::class, 'payments'),
                new CreditNoteDeletedTogether($batch),
            ],
        ];
    }
}
