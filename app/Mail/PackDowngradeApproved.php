<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Submission\SubmissionType;
use App\Models\PackChange;
use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a Pro client once Festilaw approves their switch back to the Creator Pack (SC12): it takes effect
 * at their next renewal (1 January of the effective year), when they'll sign their Creator Pack mandate and
 * pay the Creator price. Dispatched resiliently from ApprovePackDowngradeAction.
 */
final class PackDowngradeApproved extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission, public PackChange $downgrade) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your switch to the Festilaw :pack is confirmed', ['pack' => __(SubmissionType::Starter->label())]));
    }

    public function content(): Content
    {
        $cents = SubmissionType::Starter->annualCents();

        return new Content(view: 'emails.pack-downgrade-approved', with: [
            'pack' => __(SubmissionType::Starter->label()),
            'year' => $this->downgrade->effective_year,
            'price' => '€'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2),
            'fileUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
        ]);
    }
}
