<?php

namespace App\Platform\Pdf\Http\Admin;

use App\Platform\Http\Controller;
use App\Platform\Pdf\Application\PdfConfigurationService;
use App\Platform\Pdf\Http\Requests\PdfConfigurationRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PdfConfigurationController extends Controller
{
    public function __construct(private readonly PdfConfigurationService $configuration) {}

    /**
     * Returns the available drivers
     *
     * @throws AuthorizationException
     */
    public function getDrivers(): JsonResponse
    {
        $this->authorize('manage pdf config');

        $drivers = [
            'dompdf',
            'gotenberg',
        ];

        return response()->json($drivers);
    }

    /**
     * Return the PDF settings
     *
     * @throws AuthorizationException
     */
    public function getEnvironment(): JsonResponse
    {
        $this->authorize('manage pdf config');

        return response()->json($this->configuration->environment());
    }

    /**
     * Saves the settings
     *
     * @throws AuthorizationException
     */
    public function saveEnvironment(PdfConfigurationRequest $request): JsonResponse
    {
        $this->authorize('manage pdf config');

        $this->configuration->store($request->validated());

        return response()->json([
            'success' => 'pdf_variables_save_successfully',
        ]);
    }
}
