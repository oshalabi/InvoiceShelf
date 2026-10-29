<?php

namespace App\Domains\Contacts\Http\Requests\CustomerPortal;

use App\Domains\Contacts\Models\Address;
use App\Rules\IdnEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * What a contact is allowed to change about itself from inside the portal.
 *
 * Nothing here is required: the payload is a patch, so every key is optional and
 * only what was actually sent gets validated. Beyond the profile fields the form
 * carries two optional address blocks and the avatar controls, and the accessors
 * at the bottom hand the controller each of those pieces ready to store.
 */
class CustomerProfileRequest extends FormRequest
{
    /**
     * The keys accepted inside the `billing` and `shipping` blocks.
     *
     * @var list<string>
     */
    private const ADDRESS_KEYS = [
        'name',
        'address_street_1',
        'address_street_2',
        'city',
        'state',
        'country_id',
        'zip',
        'phone',
        'fax',
    ];

    /**
     * The portal guard has already vetted the caller, and a contact is only
     * ever handed its own record, so there is nothing further to check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Constraints applied to the submitted profile patch.
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['nullable'],
            'password' => ['nullable', 'min:8'],
            'email' => ['nullable', new IdnEmail, $this->emailIsFree()],
        ];

        foreach (['billing', 'shipping'] as $block) {
            foreach (self::ADDRESS_KEYS as $key) {
                $rules["{$block}.{$key}"] = ['nullable'];
            }
        }

        $rules['customer_avatar'] = ['nullable', 'file', 'mimes:gif,jpg,png', 'max:20000'];
        $rules['is_customer_avatar_removed'] = ['nullable', 'boolean'];

        return $rules;
    }

    /**
     * The profile columns the controller may hand straight to the model.
     *
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        return $this->safe()->only([
            'name',
            'email',
            'password',
        ]);
    }

    /**
     * The submitted shipping block, or null when none was sent.
     *
     * @return array<string, mixed>|null
     */
    public function shippingAddress(): ?array
    {
        return $this->addressBlock('shipping', Address::SHIPPING_TYPE);
    }

    /**
     * The submitted billing block, or null when none was sent.
     *
     * @return array<string, mixed>|null
     */
    public function billingAddress(): ?array
    {
        return $this->addressBlock('billing', Address::BILLING_TYPE);
    }

    /**
     * No two contacts of the same company may share an address.
     *
     * Both operands are read from the ambient request exactly as they always
     * have been: the tenant comes from the `company` request header (which the
     * slug-scoped portal routes do not send) and the row to skip comes from the
     * default guard rather than the portal one. Left untouched deliberately.
     */
    private function emailIsFree(): Unique
    {
        return Rule::unique('customers')
            ->where('company_id', $this->header('company'))
            ->ignore(auth()->id(), 'id');
    }

    /**
     * Lift one address block off the payload and stamp its type onto it.
     *
     * Anything that did not arrive as an array - absent, null, a scalar - comes
     * back as null, which is how the caller tells "replace this address" apart
     * from "leave this address alone".
     *
     * @return array<string, mixed>|null
     */
    private function addressBlock(string $block, string $type): ?array
    {
        $submitted = $this->input($block);

        if (! is_array($submitted)) {
            return null;
        }

        return collect($submitted)
            ->merge(['type' => $type])
            ->toArray();
    }
}
