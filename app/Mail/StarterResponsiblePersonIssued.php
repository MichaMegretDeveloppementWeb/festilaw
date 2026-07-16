<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoye au client quand son adresse de Personne Responsable UE est delivree (dossier finalise).
 */
final class StarterResponsiblePersonIssued extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your EU Responsible Person is now live'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.starter-responsible-person-issued', with: [
            'firstName' => $this->submission->first_name,
            'address' => (string) $this->submission->eu_rp_address,
            'fileUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
        ]);
    }
}
