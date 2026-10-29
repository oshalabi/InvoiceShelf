<?php

namespace App\Domains\Accounts\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password reset mail sent to staff accounts.
 *
 * The framework's own version links to a named route; this one points at the
 * SPA reset screen and quotes the configured token lifetime.
 */
class MailResetPasswordNotification extends ResetPassword
{
    use Queueable;

    /**
     * Carry the reset token through to the parent notification.
     *
     * @return void
     */
    public function __construct($token)
    {
        parent::__construct($token);
    }

    /**
     * This notification goes out over mail only.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the reset mail, linking at the SPA screen that takes the token.
     */
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = url('/reset-password/'.$this->token);

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('Hello! You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $resetUrl)
            ->line('This password reset link will expire in '.config('auth.passwords.users.expire').' minutes')
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * Nothing is stored for the database channel.
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
