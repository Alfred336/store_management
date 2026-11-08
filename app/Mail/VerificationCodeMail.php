<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to a user containing their 6-digit verification code.
 * Sent synchronously (does not implement ShouldQueue).
 */
class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your email address — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verification-code',
            text: 'mail.verification-code-text',
        );
    }
}
