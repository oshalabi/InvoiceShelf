<?php

namespace App\Domains\Reporting\Http\Resources;

use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statement = $this->resource;

        $data = [
            'type' => $statement['type'],
            'customer' => new CustomerResource($statement['customer']),
            'currency' => new CurrencyResource($statement['currency']),
        ];

        if ($statement['type'] === 'activity') {
            /** @var LengthAwarePaginator $entries */
            $entries = $statement['entries'];
            $items = array_values(collect($entries->items())->map(fn (array $entry): array => [
                'id' => (int) $entry['id'],
                'date' => (string) $entry['date'],
                'entry_type' => (string) $entry['entry_type'],
                'reference' => (string) $entry['reference'],
                'description' => (string) $entry['description'],
                'debit_amount' => (int) $entry['debit_amount'],
                'credit_amount' => (int) $entry['credit_amount'],
                'base_debit_amount' => (int) $entry['base_debit_amount'],
                'base_credit_amount' => (int) $entry['base_credit_amount'],
                'balance' => (int) $entry['balance'],
                'base_balance' => (int) $entry['base_balance'],
            ])->all());

            return array_merge($data, [
                'from_date' => (string) $statement['from_date'],
                'to_date' => (string) $statement['to_date'],
                'opening_balance' => (int) $statement['opening_balance'],
                'base_opening_balance' => (int) $statement['base_opening_balance'],
                'closing_balance' => (int) $statement['closing_balance'],
                'base_closing_balance' => (int) $statement['base_closing_balance'],
                'entries' => $items,
                'meta' => [
                    'current_page' => (int) $entries->currentPage(),
                    'last_page' => (int) $entries->lastPage(),
                    'per_page' => (int) $entries->perPage(),
                    'total' => (int) $entries->total(),
                ],
            ]);
        }

        $invoices = array_values(collect($statement['invoices'])->map(fn (array $invoice): array => [
            'id' => (int) $invoice['id'],
            'invoice_number' => (string) $invoice['invoice_number'],
            'invoice_date' => (string) $invoice['invoice_date'],
            'due_date' => $invoice['due_date'] === null ? null : (string) $invoice['due_date'],
            'original_amount' => (int) $invoice['original_amount'],
            'allocated_amount' => (int) $invoice['allocated_amount'],
            'credit_amount' => (int) $invoice['credit_amount'],
            'applied_amount' => (int) $invoice['applied_amount'],
            'remaining_amount' => (int) $invoice['remaining_amount'],
            'base_original_amount' => (int) $invoice['base_original_amount'],
            'base_allocated_amount' => (int) $invoice['base_allocated_amount'],
            'base_credit_amount' => (int) $invoice['base_credit_amount'],
            'base_applied_amount' => (int) $invoice['base_applied_amount'],
            'base_remaining_amount' => (int) $invoice['base_remaining_amount'],
        ])->all());
        $credits = array_values(collect($statement['credits'])->map(fn (array $credit): array => [
            'id' => (int) $credit['id'],
            'payment_number' => (string) $credit['payment_number'],
            'payment_date' => (string) $credit['payment_date'],
            'amount' => (int) $credit['amount'],
            'allocated_amount' => (int) $credit['allocated_amount'],
            'available_amount' => (int) $credit['available_amount'],
            'base_amount' => (int) $credit['base_amount'],
            'base_allocated_amount' => (int) $credit['base_allocated_amount'],
            'base_available_amount' => (int) $credit['base_available_amount'],
        ])->all());

        return array_merge($data, [
            'as_of' => (string) $statement['as_of'],
            'invoices' => $invoices,
            'credits' => $credits,
            'invoice_due_amount' => (int) $statement['invoice_due_amount'],
            'base_invoice_due_amount' => (int) $statement['base_invoice_due_amount'],
            'available_credit' => (int) $statement['available_credit'],
            'base_available_credit' => (int) $statement['base_available_credit'],
            'account_balance' => (int) $statement['account_balance'],
            'base_account_balance' => (int) $statement['base_account_balance'],
        ]);
    }
}
