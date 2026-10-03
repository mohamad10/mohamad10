<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/** Emails the team when a visitor sends a message (or the chat assistant captures a lead). */
class NewContactMessage extends Notification
{
    public function __construct(public ContactMessage $message) {}

    public static function dispatchTo(ContactMessage $message): void
    {
        if ($to = config('mail.notify_address')) {
            rescue(fn () => Notifier::route('mail', $to)->notify(new self($message)), report: true);
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->message;

        return (new MailMessage)
            ->subject('New message: '.($m->subject ?: $m->name))
            ->replyTo($m->email, $m->name)
            ->line("From: {$m->name} <{$m->email}>")
            ->line($m->body)
            ->action('Open admin panel', url('/admin'));
    }
}
