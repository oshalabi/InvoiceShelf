<?php

namespace App\Domains\Purchases\Http\Requests;

use App\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;

class UploadExpenseReceiptRequest extends FormRequest
{
    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for the base64 receipt payload.
     */
    public function rules(): array
    {
        return [
            'attachment_receipt' => ['nullable', new Base64Mime(['gif', 'jpg', 'png'])],
        ];
    }
}
