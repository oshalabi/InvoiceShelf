<?php

namespace App\Platform\Modules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:zip', 'max:20000'],
            'module' => ['required', 'string', 'max:100'],
        ];
    }
}
