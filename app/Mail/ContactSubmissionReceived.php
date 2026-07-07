<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New contact request from '.$this->submission->first_name,
            replyTo: [new Address($this->submission->email, (string) $this->submission->first_name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-submission',
        );
    }
}
