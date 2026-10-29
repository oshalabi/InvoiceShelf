<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseCategoryRequest extends FormRequest
{
    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for creating or editing an expense category.
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'description' => ['nullable'],
        ];
    }

    public function getExpenseCategoryPayload()
    {
        return array_merge($this->validated(), [
            'company_id' => $this->header('company'),
        ]);
    }
}
