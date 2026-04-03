<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseOcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'receipt' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
            'expense_id' => [
                'nullable',
                'integer',
                'exists:expenses,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.required' => 'Please upload a receipt to run OCR autofill.',
            'receipt.file' => 'The uploaded OCR receipt must be a valid file.',
            'receipt.mimes' => 'Unsupported file type. Please upload PDF or image.',
            'receipt.max' => 'The OCR receipt may not be greater than 10 MB.',
            'expense_id.exists' => 'The selected expense could not be found.',
        ];
    }
}
