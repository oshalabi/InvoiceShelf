<?php

namespace App\Domains\Receivables\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to collect a document online.
 *
 * Gateway modules own the lifecycle: they open a row when the payer is handed
 * off, then close it as settled or as refused. A row that settled may have a
 * receipt hanging off it, and the public hash it carries is what the payer's
 * link is built from.
 */
class Transaction extends Model
{
    use HasFactory;

    /**
     * The attempt was refused.
     */
    public const FAILED = 'FAILED';

    /**
     * The attempt went through.
     */
    public const SUCCESS = 'SUCCESS';

    protected $table = 'transactions';

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Columns the pre-cast date handling used to hydrate as instances.
     *
     * @var array
     */
    protected $dates = [
        'transaction_date',
    ];

    /**
     * Receipts booked out of this attempt.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'transaction_id');
    }

    /**
     * Document the payer was settling.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Company the attempt was made against.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Whether the payer's link has gone stale.
     *
     * Three things have to line up: the company has to have asked for public
     * links to expire at all, the attempt has to have succeeded, and more days
     * than the configured window have to have gone by. Two of those are worth
     * spelling out, because both are deliberate and neither is what the name
     * suggests. The clock runs from the row's last update rather than from
     * when it was opened, so anything that touches the row pushes the deadline
     * out; and only a successful attempt ever expires, leaving a refused one
     * reachable for good. Both dates are compared as plain calendar days.
     */
    public function isExpired(): bool
    {
        $window = (int) CompanySetting::getSetting('link_expiry_days', $this->company_id);
        $expiryEnabled = CompanySetting::getSetting('automatically_expire_public_links', $this->company_id);

        $deadline = $this->updated_at->addDays($window);

        if ($expiryEnabled != 'YES' || $this->status != self::SUCCESS) {
            return false;
        }

        return Carbon::now()->format('Y-m-d') > $deadline->format('Y-m-d');
    }
}
