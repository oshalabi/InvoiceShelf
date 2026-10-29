<?php

namespace App\Domains\Metadata\Models;

use App\Domains\Accounts\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note template a company keeps for reuse, tied to one document family
 * through `type` and optionally marked as that family's default.
 *
 * Nothing is cast: `is_default` leaves for the payload exactly as the driver
 * handed it over, and the body is stored and served as written.
 */
class Note extends Model
{
    use HasFactory;

    protected $table = 'notes';

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The two narrowings the notes screen offers. Each is taken for its truth
     * value, so a filter of "0" cannot be told apart from one never sent.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $type = $filters['type'] ?? null;

        if ($type) {
            $query->whereType($type);
        }

        $search = $filters['search'] ?? null;

        if ($search) {
            $query->whereSearch($search);
        }
    }

    public function scopeWhereSearch(Builder $query, string $search): void
    {
        $query->where('name', 'LIKE', "%{$search}%");
    }

    public function scopeWhereType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Restrict to the company the request is acting for. The column is
     * table-qualified so the scope survives being hung off a joined query.
     */
    public function scopeWhereCompany(Builder $query): void
    {
        $company = request()->header('company');

        $query->where('notes.company_id', $company);
    }
}
