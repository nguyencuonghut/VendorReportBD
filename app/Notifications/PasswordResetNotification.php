<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $resetUrl;
    public $locale;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $resetUrl, string $locale = 'vi')
    {
        $this->resetUrl = $resetUrl;
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
            ->subject(__('email.passwordReset.subject', [], $this->locale))
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'resetUrl' => $this->resetUrl,
                'locale' => $this->locale,
            ])
            ->text('emails.password-reset-text', [
                'user' => $notifiable,
                'resetUrl' => $this->resetUrl,
                'locale' => $this->locale,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset',
            'user_id' => $notifiable->id,
            'user_email' => $notifiable->email,
            'reset_url' => $this->resetUrl,
            'locale' => $this->locale,
            'sent_at' => now()->toISOString(),
            'expires_at' => now()->addMinutes(60)->toISOString(),
        ];
    }
}
