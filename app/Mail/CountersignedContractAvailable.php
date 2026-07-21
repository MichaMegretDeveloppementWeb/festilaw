<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Envoye au client quand Festilaw a depose le contrat contresigne sur son dossier : lien vers son
 * espace + le PDF contresigne en piece jointe. Envoi non bloquant depuis l'action admin.
 */
final class CountersignedContractAvailable extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your countersigned Festilaw contract'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.countersigned-contract-available', with: [
            'dossierUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $path = (string) ($this->submission->contract?->countersigned_file_path ?? '');

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $path)
                ->as('festilaw-contract-'.$this->submission->reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
