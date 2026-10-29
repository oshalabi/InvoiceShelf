<?php

namespace App\Platform\Modules\Infrastructure;

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use InvoiceShelf\Modules\Contracts\Host\ModuleAuthorization;
use LogicException;
use Silber\Bouncer\BouncerFacade;

class BouncerModuleAuthorization implements ModuleAuthorization
{
    /** @var array<string, class-string> */
    private const RESOURCE_MODELS = [
        'customer' => Customer::class,
        'invoice' => Invoice::class,
        'expense' => Expense::class,
        'payment' => Payment::class,
        'item' => Item::class,
    ];

    public function allows(int $userId, int $companyId, string $ability, ?string $resource = null): bool
    {
        $user = User::query()
            ->whereKey($userId)
            ->whereHas('companies', fn ($query) => $query->whereKey($companyId))
            ->first();

        if ($user === null) {
            return false;
        }

        if ($resource === null) {
            return BouncerFacade::scope()->onceTo($companyId, fn (): bool => $user->can($ability));
        }

        $model = self::RESOURCE_MODELS[$resource] ?? throw new LogicException("Unknown module resource: {$resource}");

        // Module calls are not necessarily made from an HTTP request, so they
        // cannot rely on ScopeBouncer middleware having established this scope.
        // Keep the caller's scope intact for long-running workers and tests.
        return BouncerFacade::scope()->onceTo($companyId, fn (): bool => $user->can($ability, $model));
    }
}
