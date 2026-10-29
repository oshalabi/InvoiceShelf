<?php

namespace App\Domains\Contacts\Http\Controllers;

use App\Domains\Contacts\Http\Resources\CountryResource;
use App\Domains\Contacts\Models\Country;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * The country reference list: every row, unfiltered and unpaginated.
 *
 * Mounted twice — once on the company API, once inside the customer portal —
 * which is why the request is accepted and never read. Neither caller narrows
 * the list; both want the whole thing to fill a picker.
 */
class CountriesController extends Controller
{
    public function __invoke(Request $request)
    {
        return CountryResource::collection(Country::all());
    }
}
