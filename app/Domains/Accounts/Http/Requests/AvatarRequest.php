<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a profile picture on its way in.
 *
 * The endpoint takes a picture by either of two routes and neither is demanded:
 * a multipart upload under `admin_avatar`, or a base64 JSON blob under
 * `avatar`. The same call also carries the removal flag, which needs no
 * picture at all — hence both fields being optional.
 */
class AvatarRequest extends FormRequest
{
    /** Picture formats accepted, whichever route the picture arrives by. */
    private const ACCEPTED_FORMATS = ['gif', 'jpg', 'png'];

    /** Ceiling on an uploaded file, in kilobytes. */
    private const MAX_KILOBYTES = 20000;

    /**
     * The caller is editing their own profile, so there is nothing to weigh up.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'admin_avatar' => [
                'nullable',
                'file',
                'mimes:'.implode(',', self::ACCEPTED_FORMATS),
                'max:'.self::MAX_KILOBYTES,
            ],
            'avatar' => [
                'nullable',
                new Base64Mime(self::ACCEPTED_FORMATS),
            ],
        ];
    }
}
