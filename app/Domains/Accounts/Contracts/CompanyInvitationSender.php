<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\CompanyInvitation;

interface CompanyInvitationSender
{
    public function send(CompanyInvitation $invitation): void;
}
