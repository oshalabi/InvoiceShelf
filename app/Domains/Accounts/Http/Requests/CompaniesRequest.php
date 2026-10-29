<?php

namespace App\Domains\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The form behind opening a new company.
 *
 * Two things are actually demanded — a name nobody in the installation has
 * taken, and a currency — plus a country whenever an address block is in play.
 * Everything else about the address is waved through unchecked.
 */
class CompaniesRequest extends FormRequest
{
    /** The address block, every field of it optional bar the country. */
    private const OPTIONAL_ADDRESS_FIELDS = [
        'name',
        'address_street_1',
        'address_street_2',
        'city',
        'state',
    ];

    private const TRAILING_ADDRESS_FIELDS = [
        'zip',
        'phone',
        'fax',
    ];

    /** Columns lifted off the validated payload onto the new company row. */
    private const COMPANY_FIELDS = [
        'name',
        'vat_id',
        'tax_id',
    ];

    /**
     * Whether the caller may open a company at all is a gate question, asked
     * in the controller; nothing is decided at this layer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Assembled in declaration order so the error bag keeps the order it has
     * always come back in: name, currency, then the address block with the
     * country sitting between the optional fields either side of it.
     *
     * The country rule is unconditional — a payload with no address block at
     * all is rejected for the missing country.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                Rule::unique('companies'),
                'string',
            ],
            'currency' => [
                'required',
            ],
        ];

        foreach (self::OPTIONAL_ADDRESS_FIELDS as $field) {
            $rules['address.'.$field] = ['nullable'];
        }

        $rules['address.country_id'] = ['required'];

        foreach (self::TRAILING_ADDRESS_FIELDS as $field) {
            $rules['address.'.$field] = ['nullable'];
        }

        return $rules;
    }

    /**
     * The row to insert: the allow-listed columns, with the caller stamped on
     * as owner and a slug derived from the name.
     *
     * The two tax identifiers carry no rule of their own, so they are never
     * part of the validated payload and can never be written through here.
     * Listed all the same, as found.
     */
    public function getCompanyPayload()
    {
        return array_merge(
            Arr::only($this->validated(), self::COMPANY_FIELDS),
            [
                'owner_id' => $this->user()->id,
                'slug' => Str::slug($this->name),
            ]
        );
    }
}
