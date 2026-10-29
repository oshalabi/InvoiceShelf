<?php

namespace App\Domains\Accounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One preference belonging to a staff account.
 *
 * The store mirrors the company one, addressed by a `key` column instead of an
 * `option` column. The sentinel value "default" on the `language` key means
 * "follow the company", so promoting a member never freezes a copy of the
 * inviter's language.
 */
class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'user_settings';

    protected $guarded = ['id'];

    /**
     * Account this preference belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
