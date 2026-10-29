<?php

namespace App\Platform\Mail\Models;

use App\Domains\Accounts\Models\CompanySetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One outbound document mail. Its token is the credential handed out in the
 * public `/…/{token}` links, so this row is what a recipient's access to a
 * document hangs on.
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    protected $guarded = ['id'];

    /**
     * The document this mail was sent about, referenced through the model
     * alias map rather than a class name.
     */
    public function mailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Decide whether the public link backed by this row still works.
     *
     * A link dies with its document: once the referenced record is gone the
     * link is treated as expired rather than as an error. Otherwise expiry is
     * opt-in per company — with `automatically_expire_public_links` switched on,
     * the link lasts `link_expiry_days` days from the moment this row was
     * written, compared at whole-day granularity.
     */
    public function isExpired(): bool
    {
        $document = $this->mailable;

        if (! $document) {
            return true;
        }

        $lifespanInDays = (int) CompanySetting::getSetting('link_expiry_days', $document->company_id);
        $expiryEnabled = CompanySetting::getSetting('automatically_expire_public_links', $document->company_id);

        $lastValidDay = $this->created_at->addDays($lifespanInDays);

        return $expiryEnabled == 'YES'
            && Carbon::now()->format('Y-m-d') > $lastValidDay->format('Y-m-d');
    }
}
