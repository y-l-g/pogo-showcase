<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class LoginLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        $loginUrl = URL::temporarySignedRoute(
            'auth.login.link',
            now()->addMinutes(5),
            // @phpstan-ignore-next-line
            ['user' => $notifiable->id]
        );

        return (new MailMessage)
            ->subject(Lang::get('Your Login Link'))
            ->line(Lang::get('You recently tried to sign in using a social account, but an account with your email address already exists.'))
            ->line(Lang::get('Click the button below to log in securely to your account. You can then link your social accounts from your profile page.'))
            ->action(Lang::get('Log In'), $loginUrl)
            ->line(Lang::get('This link will expire in 5 minutes.'))
            ->line(Lang::get('If you did not request this, you can safely ignore this email.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
