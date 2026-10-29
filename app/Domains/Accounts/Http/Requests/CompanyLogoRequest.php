<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The envelope carrying a new company logo.
 *
 * One optional field: a JSON document holding a file name and a data URI. When
 * it is there it has to name a raster image the branding collection accepts,
 * and the bytes behind the data URI have to agree with that name.
 *
 * The removal flag travels in the same payload but is deliberately unchecked —
 * the controller reads it straight off the request.
 */
class CompanyLogoRequest extends FormRequest
{
    /** Image types the logo may be. */
    private const ALLOWED_TYPES = ['gif', 'jpg', 'png'];

    /**
     * Ownership of the company is settled by the gate in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_logo' => [
                'nullable',
                new Base64Mime(self::ALLOWED_TYPES),
            ],
        ];
    }
}
