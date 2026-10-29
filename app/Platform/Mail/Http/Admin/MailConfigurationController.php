<?php

namespace App\Platform\Mail\Http\Admin;

use App\Platform\Http\Controller;
use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Mail\Http\Requests\MailEnvironmentRequest;
use App\Platform\Mail\Mailables\TestMail;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Installation-wide mail transport: which driver the app sends through, the
 * credentials behind it, and a throwaway probe message to prove it works.
 */
class MailConfigurationController extends Controller
{
    /**
     * Gate ability guarding every endpoint on this controller.
     */
    private const ABILITY = 'manage email config';

    /**
     * Onboarding wizard step recorded once a transport has been stored.
     */
    private const WIZARD_STEP = 4;

    public function __construct(
        private readonly MailConfigurationService $mailConfigurationService
    ) {}

    /**
     * Store the submitted transport settings. An installation that has not yet
     * finished onboarding is nudged forward to the mail step of the wizard.
     */
    public function saveMailEnvironment(MailEnvironmentRequest $request): JsonResponse
    {
        $this->authorize(self::ABILITY);

        $profileState = Setting::getSetting('profile_complete');

        $this->mailConfigurationService->saveGlobalConfig(
            $request->validated()
        );

        if ($profileState !== 'COMPLETED') {
            Setting::setSetting('profile_complete', self::WIZARD_STEP);
        }

        return response()->json(['success' => 'mail_variables_save_successfully']);
    }

    /**
     * Read back the stored installation-wide transport settings.
     */
    public function getMailEnvironment(): JsonResponse
    {
        $this->authorize(self::ABILITY);

        return response()->json(
            $this->mailConfigurationService->getGlobalConfig()
        );
    }

    /**
     * List the transports this installation is actually able to send through.
     */
    public function getMailDrivers(): JsonResponse
    {
        $this->authorize(self::ABILITY);

        return response()->json(
            $this->mailConfigurationService->getAvailableDrivers()
        );
    }

    /**
     * Deliver a one-off message through the active transport so an admin can
     * confirm the credentials they just saved really work.
     */
    public function testEmailConfig(Request $request): JsonResponse
    {
        $this->authorize(self::ABILITY);

        $this->validate($request, [
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        $probe = new TestMail($request->subject, $request->message);

        Mail::to($request->to)->send($probe);

        return response()->json(['success' => true]);
    }
}
