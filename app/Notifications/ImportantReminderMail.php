<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email copy of a reminder for a plan marked "important". */
class ImportantReminderMail extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->body)
            ->action(__('planner.reminders.open'), $this->url);
    }
}
