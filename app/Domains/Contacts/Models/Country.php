<?php

namespace App\Domains\Contacts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A row of the global country reference list.
 *
 * The table is seeded once and never written to by the application: it only
 * supplies the identifier, ISO code, display name and dialling prefix that
 * addresses point at.
 */
class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    /**
     * Every address that points at this country.
     */
    public function address(): HasMany
    {
        return $this->hasMany(Address::class, 'country_id');
    }
}
