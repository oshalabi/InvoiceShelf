<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Models\Estimate;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Http\Request;

class EstimateTemplatesController extends Controller
{
    /**
     * List the PDF templates an estimate can be rendered with, each already
     * paired with its preview image.
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        return response()->json([
            'estimateTemplates' => PdfTemplateUtils::getFormattedTemplates('estimate'),
        ]);
    }
}
