<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTemplatesController extends Controller
{
    /**
     * List the PDF templates an invoice can be rendered with, each already
     * paired with its preview image.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        return response()->json([
            'invoiceTemplates' => PdfTemplateUtils::getFormattedTemplates('invoice'),
        ]);
    }
}
