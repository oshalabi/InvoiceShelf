<?php

namespace App\Domains\Money\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History of the rates documents were priced with.
 *
 * The two currency columns read backwards: `base_currency_id` holds the
 * currency of the document, `currency_id` the company's own base currency as
 * it stood when the row was written. Readers depend on that direction.
 */
class ExchangeRateLog extends Model
{
    use HasFactory;

    protected $table = 'exchange_rate_logs';

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'float',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Log the rate a priced record (invoice, estimate, expense, payment) was
     * captured with. The company side of the pair is read from settings.
     */
    public static function addExchangeRateLog(mixed $model): self
    {
        return static::create([
            'exchange_rate' => $model->exchange_rate,
            'company_id' => $model->company_id,
            'base_currency_id' => $model->currency_id,
            'currency_id' => CompanySetting::getSetting('currency', $model->company_id),
        ]);
    }
}
