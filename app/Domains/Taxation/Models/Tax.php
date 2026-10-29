<?php

namespace App\Domains\Taxation\Models;

use App\Domains\Catalog\Models\Item;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\EstimateItem;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One tax applied to one thing.
 *
 * The row is a snapshot: name, percent, calculation type, fixed amount and the
 * compound flag are copied off the tax type when the tax is applied, so later
 * edits to the type never rewrite history on documents that already used it.
 * Exactly one of the owner columns is populated per row -- a document, one of
 * its line items, a recurring invoice, an expense or a catalog item -- which is
 * why every owner relation below is nullable in practice.
 *
 * `amount` is signed minor units (credit notes go negative); `base_amount` is
 * the same figure converted to the company's base currency.
 */
class Tax extends Model
{
    use HasFactory;

    protected $table = 'taxes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'percent' => 'float',
            'fixed_amount' => 'integer',
            'compound_tax' => 'boolean',
        ];
    }

    /**
     * The type this row was snapshotted from. Deleting a referenced type is
     * refused, so this reference can be dereferenced without a null check.
     */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function estimateItem(): BelongsTo
    {
        return $this->belongsTo(EstimateItem::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeWhereCompany(Builder $query, int $company_id): void
    {
        $query->where('company_id', $company_id);
    }

    /**
     * Collapse the result set to one row per tax type, carrying the summed
     * base amount. The select list is replaced outright, so a query using this
     * scope hands back the aggregate columns and nothing else.
     */
    public function scopeTaxAttributes(Builder $query): void
    {
        $query->select(DB::raw('sum(base_amount) as total_tax_amount, tax_type_id'))
            ->groupBy('tax_type_id');
    }

    /**
     * Reporting filter: taxes that belong to paid invoices dated in the range.
     * Both ends are required -- a half-open range narrows nothing.
     */
    public function scopeWhereInvoicesFilters(Builder $query, array $filters): void
    {
        if ($range = $this->closedDateRange($filters)) {
            $query->invoicesBetween(...$range);
        }
    }

    /**
     * A tax can hang off the invoice itself or off one of its line items, so
     * both routes to the parent document are considered; the pair is wrapped
     * in its own group to keep the OR from leaking into surrounding clauses.
     */
    public function scopeInvoicesBetween(Builder $query, Carbon $start, Carbon $end): void
    {
        $paidWithin = function (Builder $invoices) use ($start, $end): void {
            $invoices->where('paid_status', Invoice::STATUS_PAID)
                ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        };

        $query->where(function (Builder $taxes) use ($paidWithin): void {
            $taxes->whereHas('invoice', $paidWithin)
                ->orWhereHas('invoiceItem.invoice', $paidWithin);
        });
    }

    /**
     * Reporting filter: taxes on expenses dated in the range. Expenses have no
     * paid state, so unlike invoices there is no status condition here.
     */
    public function scopeWhereExpensesFilters(Builder $query, array $filters): void
    {
        if ($range = $this->closedDateRange($filters)) {
            $query->expensesBetween(...$range);
        }
    }

    public function scopeExpensesBetween(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->whereHas('expense', function (Builder $expenses) use ($start, $end): void {
            $expenses->whereBetween('expense_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        });
    }

    /**
     * Turn the `from_date` / `to_date` filter pair into Carbon bounds, or null
     * when either end is missing.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function closedDateRange(array $filters): ?array
    {
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if (! $from || ! $to) {
            return null;
        }

        return [
            Carbon::createFromFormat('Y-m-d', $from),
            Carbon::createFromFormat('Y-m-d', $to),
        ];
    }
}
