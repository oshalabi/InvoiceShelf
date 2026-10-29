<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Taxation\Models\Tax;
use App\Support\SafeOrderBy;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Money going out of a company.
 *
 * The record is deliberately plain next to the sales documents: there is no
 * numbering service behind `expense_number` and nothing enforces that two
 * expenses differ, the currency is whatever the client submitted rather than
 * one inherited from a contact, and the only derived figure kept on the row is
 * `base_amount` -- the amount restated in the company's own currency.
 *
 * Two things hang off the row. Purchase taxes live in `taxes` as snapshots of
 * the tax type they were raised from, replaced wholesale rather than edited.
 * A receipt is a single file in the media library; the model only reports what
 * is attached, attaching and discarding are somebody else's job.
 */
class Expense extends Model implements HasMedia
{
    protected $table = 'expenses';

    use HasCustomFields;
    use HasFactory;
    use InteractsWithMedia;

    /**
     * Column the pre-cast date handling used to hydrate as an instance.
     *
     * Nothing reads this any more -- the date reaches callers as the plain
     * `Y-m-d` string the driver returns -- but the accessors below parse
     * defensively, so the column is listed as it always has been.
     *
     * @var array
     */
    protected $dates = [
        'expense_date',
    ];

    /**
     * Everything but the primary key may be mass assigned.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Computed attributes, listed in the order they are serialized.
     *
     * @var array
     */
    protected $appends = [
        'formattedExpenseDate',
        'formattedCreatedAt',
        'receipt',
        'receiptMeta',
    ];

    /**
     * Attribute casts.
     *
     * The money columns are left alone -- they are whole minor units already.
     * Only the free-text note and the fractional exchange rate are pinned, the
     * latter because the rate multiplies the amount and must not arrive as a
     * string.
     */
    protected function casts(): array
    {
        return [
            'notes' => 'string',
            'exchange_rate' => 'float',
        ];
    }

    /**
     * The one media collection an expense owns.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Heading the spend was filed under.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Contact the spend was made on behalf of, when one was named.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Company the expense was booked under.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * How the money left, when it was recorded.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Currency the expense was entered in -- the client's choice, kept as sent.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Staff account that recorded the expense.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Purchase taxes raised against this expense, each a snapshot of the type
     * it came from.
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'expense_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Date of the spend in the company's configured date format, written in
     * the language the application is running in.
     */
    public function getFormattedExpenseDateAttribute(mixed $value): string
    {
        $moment = Carbon::parse($this->expense_date);

        return $moment->translatedFormat($this->companyDateFormat());
    }

    /**
     * Creation timestamp in the company's configured date format, written in
     * the language the application is running in.
     */
    public function getFormattedCreatedAtAttribute(mixed $value): string
    {
        $moment = Carbon::parse($this->created_at);

        return $moment->translatedFormat($this->companyDateFormat());
    }

    /**
     * Where the receipt can be fetched from and what kind of file it is, or
     * null when nothing is attached.
     *
     * The link is a relative path rather than a full URL, and it is the
     * expense id -- not the media id -- that identifies the file, which is why
     * a replacement is reachable at the same address.
     */
    public function getReceiptUrlAttribute(mixed $value): ?array
    {
        $receipt = $this->receiptMedia();

        if (! $receipt) {
            return null;
        }

        return [
            'url' => '/reports/expenses/'.$this->id.'/receipt',
            'type' => $receipt->type,
        ];
    }

    /**
     * Absolute path of the attached receipt on whichever disk holds it, or
     * null when nothing is attached.
     */
    public function getReceiptAttribute(mixed $value): ?string
    {
        $receipt = $this->receiptMedia();

        if (! $receipt) {
            return null;
        }

        return $receipt->getPath();
    }

    /**
     * The receipt's media record itself, serialized alongside the expense so a
     * client can read the file name, size and mime type without a second call.
     */
    public function getReceiptMetaAttribute(mixed $value): ?Media
    {
        return $this->receiptMedia();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Restrict to expenses dated inside the inclusive range.
     */
    public function scopeExpensesBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween($this->qualifyColumn('expense_date'), [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

    /**
     * Keep only expenses whose category name contains every
     * whitespace-separated term.
     */
    public function scopeWhereCategoryName(Builder $query, string $search): void
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $needle = self::wildcard($term);

            $query->whereHas('category', fn ($heading) => $heading->where('name', 'LIKE', $needle));
        }
    }

    /**
     * Partial match on the free-text note.
     */
    public function scopeWhereNotes(Builder $query, string $search): void
    {
        $query->where('notes', 'LIKE', self::wildcard($search));
    }

    /**
     * Narrow to one heading.
     */
    public function scopeWhereCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where($this->qualifyColumn('expense_category_id'), $categoryId);
    }

    /**
     * Narrow to one contact.
     */
    public function scopeWhereUser(Builder $query, int $customer_id): Builder
    {
        return $query->where($this->qualifyColumn('customer_id'), $customer_id);
    }

    /**
     * Run every listed filter that carries a value.
     *
     * Order is load-bearing: the clauses land in the query in the order
     * written here, and both the expense-id filter and the search contribute
     * an OR, which makes everything queued before them part of that
     * alternative. A filter sent as an empty string, a zero or a null counts
     * as not sent at all, and a half-open date range narrows nothing.
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $clauses = [
            'expense_category_id' => fn ($wanted) => $query->whereCategory($wanted),
            'customer_id' => fn ($wanted) => $query->whereUser($wanted),
            'expense_id' => fn ($wanted) => $query->whereExpense($wanted),
        ];

        foreach ($clauses as $filter => $clause) {
            $wanted = $filters[$filter] ?? null;

            if ($wanted) {
                $clause($wanted);
            }
        }

        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if ($from && $to) {
            $query->expensesBetween(
                Carbon::createFromFormat('Y-m-d', $from),
                Carbon::createFromFormat('Y-m-d', $to)
            );
        }

        $sortField = $filters['orderByField'] ?? null;
        $sortDirection = $filters['orderBy'] ?? null;

        if ($sortField || $sortDirection) {
            $query->whereOrder($sortField ?: 'expense_date', $sortDirection ?: 'asc');
        }

        $search = $filters['search'] ?? null;

        if ($search) {
            $query->whereSearch($search);
        }
    }

    /**
     * Widen a listing to also take in one specific expense.
     *
     * The column is deliberately left unqualified: the listing query joins the
     * contacts and headings tables, so this is the clause that decides whether
     * an id filter is answerable at all.
     */
    public function scopeWhereExpense(Builder $query, int $expense_id): void
    {
        $query->orWhere('id', $expense_id);
    }

    /**
     * Free-text search over the note and the heading name.
     *
     * Each whitespace-separated term contributes two alternatives that are
     * added to the query side by side rather than grouped, so a multi-term
     * search reads as "heading OR note AND heading OR note" and widens the
     * result set instead of narrowing it. That is what the listing has always
     * done and what its callers expect.
     */
    public function scopeWhereSearch(Builder $query, string $search): void
    {
        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $needle = self::wildcard($term);

            $query->whereHas('category', fn ($heading) => $heading->where('name', 'LIKE', $needle))
                ->orWhere('notes', 'LIKE', $needle);
        }
    }

    /**
     * Sort by a caller-supplied column, sanitised before it reaches SQL and
     * falling back to the creation timestamp.
     */
    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): void
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    /**
     * Narrow to the company the current request is acting on.
     */
    public function scopeWhereCompany(Builder $query): void
    {
        $company = request()->header('company');

        $query->where($this->qualifyColumn('company_id'), $company);
    }

    /**
     * Narrow to one named company, for callers that have no request to read.
     */
    public function scopeWhereCompanyId(Builder $query, int $company): void
    {
        $query->where($this->qualifyColumn('company_id'), $company);
    }

    /**
     * Return the whole result set for the sentinel limit "all", otherwise a
     * page of the requested size.
     *
     * @return Collection|LengthAwarePaginator
     */
    public function scopePaginateData(Builder $query, string $limit)
    {
        return $limit == 'all' ? $query->get() : $query->paginate($limit);
    }

    /**
     * Collapse the result set to one row per heading, carrying the row count
     * and the summed base amount. The select list is replaced outright, so a
     * query using this scope hands back the aggregate columns and nothing else.
     */
    public function scopeExpensesAttributes(Builder $query): void
    {
        $query->select(DB::raw('count(*) as expenses_count, sum(base_amount) as total_amount, expense_category_id'))
            ->groupBy('expense_category_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The attached receipt, or null when the expense carries none. Only the
     * first file in the collection counts -- an expense has one receipt.
     */
    private function receiptMedia(): ?Media
    {
        return $this->getFirstMedia('receipts');
    }

    /**
     * The date format this company writes its records in.
     */
    private function companyDateFormat(): mixed
    {
        return CompanySetting::getSetting('carbon_date_format', $this->company_id);
    }

    /**
     * A term wrapped for a LIKE comparison.
     */
    private static function wildcard(string $term): string
    {
        return '%'.$term.'%';
    }
}
