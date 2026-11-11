<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to a user containing their temporary password.
 * Sent synchronously (does not implement ShouldQueue).
 */
class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your account is ready — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.temporary-password',
            text: 'mail.temporary-password-text',
        );
    }
}
