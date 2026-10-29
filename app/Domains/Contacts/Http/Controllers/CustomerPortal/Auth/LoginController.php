<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth;

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Http\Requests\CustomerPortal\CustomerLoginRequest;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Http\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Opens a portal session for one contact of one company.
 *
 * The company arrives as a route-bound slug, so a contact can only ever sign
 * in to the tenant whose portal they are standing in front of; the same
 * address held by a contact of another company is simply not found here.
 */
class LoginController extends Controller
{
    /**
     * Used both for an address nobody holds and for a mistyped password.
     */
    private const REJECTED_CREDENTIALS = 'The provided credentials are incorrect.';

    /**
     * Used when the contact is real but their portal switch is off.
     */
    private const PORTAL_CLOSED = 'Customer portal not available for this user.';

    /**
     * Trade a valid email/password pair for a portal session.
     */
    public function __invoke(CustomerLoginRequest $request, Company $company)
    {
        $customer = $this->contactFor($request->email, $company);

        // Credentials are weighed before the portal switch on purpose, so a
        // caller who gets both wrong is told only that the pair was wrong.
        if ($customer === null || ! Hash::check($request->password, $customer->password)) {
            $this->refuse(self::REJECTED_CREDENTIALS);
        }

        if (! $customer->enable_portal) {
            $this->refuse(self::PORTAL_CLOSED);
        }

        auth()->guard('customer')->login($customer);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Find the contact holding this address inside the given company.
     *
     * The comparison is made on the lower-cased column so that capitalising
     * an address differently from how it was stored still gets the contact
     * in, on every database engine the app supports.
     */
    private function contactFor(string $email, Company $company): ?Customer
    {
        return Customer::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->where('company_id', $company->getKey())
            ->first();
    }

    /**
     * Abort the attempt, pinning the reason to the email field.
     */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['email' => [$message]]);
    }
}
