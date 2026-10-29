<?php

namespace App\Platform\Mail\Http\Requests;

use App\Platform\Mail\Application\MailConfigurationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards an installation-wide mail transport submission, checking it against
 * the field set of whichever driver the payload names.
 */
class MailEnvironmentRequest extends FormRequest
{
    /**
     * Access is gated by the controller's ability check, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Delegate the rule set to the configuration service, which knows which
     * fields belong to the driver being submitted.
     */
    public function rules(): array
    {
        $driver = $this->string('mail_driver')->toString();

        return app(MailConfigurationService::class)->validationRules($driver);
    }
}
