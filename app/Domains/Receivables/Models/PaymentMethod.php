<?php

namespace App\Domains\Receivables\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Purchases\Models\Expense;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A way money changes hands, named per company.
 *
 * Two kinds share the table. A general method is nothing but a label an
 * operator types in and then picks from a list. A module method is registered
 * by an installed gateway and carries the driver name, its credentials and the
 * flags that say whether it is switched on and whether it is pointed at the
 * gateway's sandbox; those rows belong to the module platform and are not
 * edited through the ordinary payment-method endpoints.
 *
 * The same registry is shared by receipts and by expenses, which is why a
 * method stays undeletable while either side still refers to it.
 */
class PaymentMethod extends Model
{
    use HasFactory;

    /**
     * A label an operator maintains by hand.
     */
    public const TYPE_GENERAL = 'GENERAL';

    /**
     * An entry owned by an installed gateway module.
     */
    public const TYPE_MODULE = 'MODULE';

    protected $table = 'payment_methods';

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Attribute casts.
     *
     * Gateway credentials come back as an array; the sandbox flag as a real
     * boolean.
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'use_test_env' => 'boolean',
        ];
    }

    /**
     * Store the gateway settings as JSON.
     *
     * Writing the column by hand takes precedence over the array cast, so an
     * empty value is encoded and stored rather than skipped.
     *
     * @param  mixed  $value
     */
    public function setSettingsAttribute($value)
    {
        $encoded = json_encode($value);

        $this->attributes['settings'] = $encoded;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Receipts taken this way.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
    }

    /**
     * Expenses settled this way.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'payment_method_id');
    }

    /**
     * Company the method was created under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Run every listed filter that carries a value.
     *
     * Order is load-bearing: the method-id filter contributes an OR, which
     * makes anything queued before it part of that alternative. Note that the
     * company filter ignores the id it is handed and narrows to the company on
     * the current request instead.
     */
    public function scopeApplyFilters($query, array $filters)
    {
        $clauses = [
            'method_id' => fn ($wanted) => $query->wherePaymentMethod($wanted),
            'company_id' => fn ($wanted) => $query->whereCompany($wanted),
            'search' => fn ($wanted) => $query->whereSearch($wanted),
        ];

        foreach ($clauses as $filter => $clause) {
            $wanted = $filters[$filter] ?? null;

            if ($wanted) {
                $clause($wanted);
            }
        }
    }

    /**
     * Narrow to one company.
     */
    public function scopeWhereCompanyId($query, $id)
    {
        $query->where('company_id', '=', $id);
    }

    /**
     * Narrow to the company the current request is acting on.
     */
    public function scopeWhereCompany($query)
    {
        $company = request()->header('company');

        $query->where('company_id', '=', $company);
    }

    /**
     * Widen a listing to also take in one specific method.
     */
    public function scopeWherePaymentMethod($query, $payment_id)
    {
        $query->orWhere('id', $payment_id);
    }

    /**
     * Partial match on the method's name.
     */
    public function scopeWhereSearch($query, $search)
    {
        $needle = '%'.$search.'%';

        $query->where('name', 'LIKE', $needle);
    }

    /**
     * Return the whole result set for the sentinel limit "all", otherwise a
     * page of the requested size.
     */
    public function scopePaginateData($query, $limit)
    {
        return $limit == 'all' ? $query->get() : $query->paginate($limit);
    }

    /**
     * Retrieve the settings array for a payment method by its ID.
     */
    public static function getSettings(int $id): mixed
    {
        $method = self::find($id);

        return $method->settings;
    }
}
