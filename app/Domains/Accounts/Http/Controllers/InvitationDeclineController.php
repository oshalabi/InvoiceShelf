<?php

namespace App\Domains\Accounts\Http\Controllers;

use App\Domains\Accounts\Models\CompanyInvitation;
use App\Platform\Http\Controller;
use Illuminate\Contracts\View\View;

class InvitationDeclineController extends Controller
{
    public function __invoke(string $token): View
    {
        $invitation = CompanyInvitation::query()
            ->where('token', $token)
            ->pending()
            ->first();

        if (! $invitation) {
            return view('app')->with(['message' => 'Invitation not found or already expired.']);
        }

        $invitation->update(['status' => CompanyInvitation::STATUS_DECLINED]);

        return view('app')->with(['message' => 'Invitation declined.']);
    }
}
