<?php

namespace App\Platform\Mail\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EmailLogWriter
{
    /**
     * @param  array{from: string, to: string, cc?: string|null, bcc?: string|null, subject: string, body: string}  $message
     */
    public function record(Model $mailable, array $message): string;
}
