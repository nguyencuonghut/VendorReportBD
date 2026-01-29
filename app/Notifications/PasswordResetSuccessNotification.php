<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $locale;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $locale = 'vi')
    {
        $this->locale = $locale;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('email.passwordResetSuccess.subject', [], $this->locale))
            ->greeting(__('email.passwordResetSuccess.greeting', ['name' => $notifiable->name], $this->locale))
            ->line(__('email.passwordResetSuccess.message1', [], $this->locale))
            ->line(__('email.passwordResetSuccess.message2', [], $this->locale))
            ->action(__('email.passwordResetSuccess.loginButton', [], $this->locale), url('/login'))
            ->line(__('email.passwordResetSuccess.securityNote', [], $this->locale))
            ->salutation(__('email.passwordResetSuccess.signature', [], $this->locale));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset_success',
            'user_id' => $notifiable->id,
            'user_email' => $notifiable->email,
            'locale' => $this->locale,
            'reset_at' => now()->toISOString(),
        ];
    }
}
