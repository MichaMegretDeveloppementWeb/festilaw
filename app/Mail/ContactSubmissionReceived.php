<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Envoi synchrone (pas de ShouldQueue) : pas de worker/cron requis. Voir CreateContactSubmissionAction.
class ContactSubmissionReceived extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New contact request from :name', ['name' => (string) $this->submission->first_name]),
            replyTo: [new Address($this->submission->email, (string) $this->submission->first_name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-submission',
            with: [
                'dossierUrl' => route('admin.submissions.show', ['submission' => $this->submission->id]),
            ],
        );
    }
}
