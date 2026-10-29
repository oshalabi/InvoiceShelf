<?php

namespace App\Domains\Metadata\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reusable note template on its way in.
 *
 * A name only has to be free inside its own company and its own document
 * type, so the uniqueness lookup carries both narrowings. Editing excludes the
 * row being edited from that lookup — but only under PUT: a PATCH builds the
 * plain rule and so collides with the note's own name.
 */
class NotesRequest extends FormRequest
{
    /**
     * Every caller is welcome here; the controller is what consults the gate.
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
        $company = $this->header('company');

        $nameIsFree = $this->isMethod('PUT')
            ? Rule::unique('notes')
                ->ignore($this->route('note')->id)
                ->where('type', $this->type)
                ->where('company_id', $company)
            : Rule::unique('notes')
                ->where('company_id', $company)
                ->where('type', $this->type);

        return [
            'type' => ['required'],
            'name' => ['required', $nameIsFree],
            'notes' => ['required'],
            'is_default' => ['required'],
        ];
    }

    /**
     * The validated attributes, stamped with the company from the request
     * header. A `company_id` sent by the client is not among them — it is not
     * a validated key, so it never survives this far.
     */
    public function getNotesPayload()
    {
        $attributes = $this->validated();
        $attributes['company_id'] = $this->header('company');

        return $attributes;
    }
}
