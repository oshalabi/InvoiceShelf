<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Intl\Countries;

/**
 * A postal address attached to a customer (or, historically, to a user).
 *
 * Each address is tagged with the role it plays for its owner, and a customer
 * effectively keeps at most one address per role.
 */
class Address extends Model
{
    use HasFactory;

    /**
     * Role of an address that invoices are billed to.
     */
    public const BILLING_TYPE = 'billing';

    /**
     * Role of an address that goods are shipped to.
     */
    public const SHIPPING_TYPE = 'shipping';

    protected $table = 'addresses';

    protected $guarded = ['id'];

    /**
     * Owner of this address.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Company the address was recorded under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * User the address belongs to, for addresses owned by a team member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Country reference row this address sits in.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Country name rendered in the language the application is running in.
     *
     * Falls back to the name stored in the reference table whenever the ICU
     * catalogue has nothing for the stored code.
     */
    public function getCountryNameAttribute(): ?string
    {
        $country = $this->country;

        if (! $country) {
            return null;
        }

        try {
            return Countries::getName($country->code, app()->getLocale());
        } catch (\Exception $exception) {
            return $country->name;
        }
    }
}
