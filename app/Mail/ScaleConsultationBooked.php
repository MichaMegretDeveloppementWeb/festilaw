<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the client once they confirm their SCALE consultation booking ("I've booked"). Complements
 * Google's own calendar invitation with an application-side confirmation, and reminds them Festilaw will
 * confirm the exact slot. Dispatched resiliently from RecordAppointmentAction (only on a new booking).
 */
final class ScaleConsultationBooked extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your Festilaw consultation is booked'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.scale-consultation-booked', with: [
            'spaceUrl' => route('get-started.scale.space', ['dossier' => $this->submission->resume_token]),
        ]);
    }
}
