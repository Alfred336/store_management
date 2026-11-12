<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a quotation PDF to a customer or prospect.
 * Sent synchronously (does not implement ShouldQueue).
 */
class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly string $recipientEmail,
        public readonly string $message,
        private readonly string $pdfBytes,
        private readonly string $pdfFilename,
    ) {}

    public function envelope(): Envelope
    {
        $number = $this->quotation->number ?? 'Draft';

        return new Envelope(
            subject: 'Quotation '.$number.' — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quotation',
            text: 'mail.quotation-text',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBytes, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
