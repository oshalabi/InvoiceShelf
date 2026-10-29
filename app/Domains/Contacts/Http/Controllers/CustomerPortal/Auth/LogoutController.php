<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth;

use App\Platform\Http\Controller;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(): void
    {
        Auth::guard('customer')->logout();
    }
}
