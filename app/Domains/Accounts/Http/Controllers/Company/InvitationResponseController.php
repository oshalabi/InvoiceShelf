<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Application\InvitationService;
use App\Domains\Accounts\Http\Resources\CompanyInvitationResource;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationResponseController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {}

    /**
     * Get pending invitations for the authenticated user.
     */
    public function pending(Request $request): JsonResponse
    {
        $invitations = $this->invitationService->getPendingForUser($request->user());

        return response()->json([
            'invitations' => CompanyInvitationResource::collection($invitations),
        ]);
    }

    /**
     * Accept a company invitation.
     */
    public function accept(Request $request, CompanyInvitation $invitation): JsonResponse
    {
        $this->invitationService->accept($invitation, $request->user());

        return response()->json(['success' => true]);
    }

    /**
     * Decline a company invitation.
     */
    public function decline(Request $request, CompanyInvitation $invitation): JsonResponse
    {
        $this->invitationService->decline($invitation, $request->user());

        return response()->json(['success' => true]);
    }
}
