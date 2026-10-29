<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Http\Resources\CustomerPortal\CustomerResource;
use App\Domains\Money\Models\Currency;
use App\Platform\Http\Controller;
use App\Platform\Modules\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The single call the portal SPA makes once a portal session exists.
 *
 * It answers with the signed-in contact plus, in the response meta, the few
 * ambient facts the shell cannot render without: its navigation entries, the
 * contact's own currency, the currency the company keeps its books in, the
 * names of the enabled modules and the company's language.
 */
class BootstrapController extends Controller
{
    /**
     * Build the portal boot payload.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $customer = auth('customer')->user();

        // Flatten the registered portal menu to plain title/link pairs. The
        // per-item signed-in check is how this has always been written; it also
        // means $menu is never assigned - and therefore reads as undefined - if
        // the menu happens to carry no items at all. Left as is on purpose.
        $portalMenu = \Menu::get('customer_portal_menu');

        foreach ($portalMenu->items->toArray() as $entry) {
            if (! $customer) {
                continue;
            }

            $menu[] = [
                'title' => $entry->title,
                'link' => $entry->link->path['url'],
            ];
        }

        $companyId = $customer->company_id;
        $bookkeepingCurrency = CompanySetting::getSetting('currency', $companyId);
        $contactCurrency = Currency::find($customer->currency_id);
        $enabledModules = Module::query()->where('enabled', true)->pluck('name');

        return CustomerResource::make($customer)->additional([
            'meta' => [
                'menu' => $menu,
                'current_customer_currency' => $contactCurrency,
                'current_company_currency' => $bookkeepingCurrency ? Currency::find($bookkeepingCurrency) : null,
                'modules' => $enabledModules,
                'current_company_language' => CompanySetting::getSetting('language', $companyId),
            ],
        ]);
    }
}
