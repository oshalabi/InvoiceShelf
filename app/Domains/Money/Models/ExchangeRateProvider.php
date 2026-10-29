<?php

namespace App\Domains\Money\Models;

use App\Domains\Accounts\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One company's credentials for a rate service, together with the currency
 * codes that service is allowed to quote and its driver-specific settings.
 *
 * Both JSON columns go through mutators, so whatever a caller assigns is
 * encoded verbatim rather than left to the casts.
 */
class ExchangeRateProvider extends Model
{
    use HasFactory;

    protected $table = 'exchange_rate_providers';

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'currencies' => 'array',
            'driver_config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function setCurrenciesAttribute($value)
    {
        $this->attributes['currencies'] = json_encode($value);
    }

    public function setDriverConfigAttribute($value)
    {
        $this->attributes['driver_config'] = json_encode($value);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('exchange_rate_providers.company_id', request()->header('company'));
    }
}
