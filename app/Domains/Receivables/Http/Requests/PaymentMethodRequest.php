<?php

namespace App\Domains\Receivables\Http\Requests;

use App\Domains\Receivables\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validates a manual payment method and assembles what gets stored.
 */
class PaymentMethodRequest extends FormRequest
{
    /**
     * The payment-method abilities are checked by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', $this->uniqueName()],
        ];
    }

    /**
     * Anything these endpoints write is a manual label of the active company;
     * module-owned methods are registered elsewhere.
     */
    public function getPaymentMethodPayload()
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
                'type' => PaymentMethod::TYPE_GENERAL,
            ])
            ->toArray();
    }

    /**
     * Names are unique inside a company. A replace exempts the method being
     * written from its own name. Note that only PUT counts as a replace,
     * so a PATCH of an unchanged name collides with itself.
     */
    private function uniqueName(): Unique
    {
        $rule = Rule::unique('payment_methods')->where('company_id', $this->header('company'));

        return $this->isMethod('PUT')
            ? $rule->ignore($this->route('payment_method'), 'id')
            : $rule;
    }
}
