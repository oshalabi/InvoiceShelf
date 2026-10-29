<?php

namespace App\Platform\Mail\Mailables;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Throwaway probe an administrator sends at the freshly configured transport
 * to confirm that mail actually leaves the installation.
 */
class TestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Subject line supplied by the administrator.
     *
     * @var mixed
     */
    public $subject;

    /**
     * Body supplied by the administrator, dropped into the markdown template.
     *
     * @var mixed
     */
    public $message;

    /**
     * @param  mixed  $subject
     * @param  mixed  $message
     */
    public function __construct($subject, $message)
    {
        $this->subject = $subject;
        $this->message = $message;
    }

    /**
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subject)
            ->markdown('emails.test')
            ->with(['my_message' => $this->message]);
    }
}
