<?php

namespace App\Domains\Reporting\Http\Requests;

use App\Domains\Reporting\Queries\CustomerStatementQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStatementRequest extends FormRequest
{
    /**
     * Nothing is decided here: the controller weighs the customer and the
     * report ability before any statement is drawn up, which leaves this
     * class to shape and default the parameters it is handed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Which statement is wanted, over which window, and how much of it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([CustomerStatementQuery::TYPE_ACTIVITY, CustomerStatementQuery::TYPE_OUTSTANDING])],
            'from_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,activity'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,activity', 'after_or_equal:from_date'],
            'as_of' => ['nullable', 'date_format:Y-m-d', 'required_if:type,outstanding'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type', CustomerStatementQuery::TYPE_ACTIVITY);
        $today = Carbon::today();

        $this->merge([
            'type' => $type,
            'from_date' => $this->input('from_date', $today->copy()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', $today->toDateString()),
            'as_of' => $this->input('as_of', $today->toDateString()),
        ]);
    }
}
