<?php

namespace App\Platform\Modules\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceCredential extends Model
{
    protected $table = 'marketplace_credentials';

    protected $guarded = ['id'];

    protected $hidden = ['credential'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paired_at' => 'datetime',
        ];
    }
}
