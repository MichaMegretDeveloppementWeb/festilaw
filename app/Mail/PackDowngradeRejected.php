<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a Pro client when Festilaw cannot approve their switch back to the Creator Pack (SC12), with
 * Festilaw's optional message. Their Pro Pack continues. Dispatched resiliently from RejectPackDowngradeAction.
 */
final class PackDowngradeRejected extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission, public ?string $note) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('About your request to switch to the Festilaw :pack', ['pack' => __(SubmissionType::Starter->label())]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pack-downgrade-rejected', with: [
            'pack' => __(SubmissionType::Starter->label()),
            'currentPack' => __($this->submission->type->label()),
            'fileUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
        ]);
    }
}
