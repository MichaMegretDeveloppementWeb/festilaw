<?php

declare(strict_types=1);

namespace App\Actions\Web\Contact;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\ContactSubmissionReceived;
use App\Models\Submission;
use Illuminate\Support\Facades\Mail;

/**
 * @phpstan-type ContactData array{name: string, email: string, website_url?: string|null, message: string}
 */
final readonly class CreateContactSubmissionAction
{
    /** @param  ContactData  $data */
    public function execute(array $data): Submission
    {
        // Ecriture unique : pas de transaction (cf. architecture-couches, pragmatisme).
        $submission = Submission::create([
            'type' => SubmissionType::Contact,
            'status' => SubmissionStatus::New,
            'locale' => app()->getLocale(),
            'first_name' => $data['name'],
            'email' => $data['email'],
            'website_url' => ($data['website_url'] ?? '') !== '' ? $data['website_url'] : null,
            'message' => $data['message'],
        ]);

        // Notification synchrone a Festilaw, apres commit (pas de file/worker).
        Mail::to(config('festilaw.notification_email'))
            ->send(new ContactSubmissionReceived($submission));

        return $submission;
    }
}
