<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Message libre (objet + corps) ecrit par l'equipe et envoye au client depuis le back-office. */
final class AdminMessageToClient extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-message-to-client', with: [
            'bodyText' => $this->bodyText,
        ]);
    }
}
