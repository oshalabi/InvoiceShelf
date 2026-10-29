<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\CompanyInvitationSender;
use App\Domains\Accounts\Mail\CompanyInvitationMail;
use App\Domains\Accounts\Models\CompanyInvitation;
use Illuminate\Support\Facades\Mail;

class LaravelCompanyInvitationSender implements CompanyInvitationSender
{
    public function send(CompanyInvitation $invitation): void
    {
        Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation));
    }
}
