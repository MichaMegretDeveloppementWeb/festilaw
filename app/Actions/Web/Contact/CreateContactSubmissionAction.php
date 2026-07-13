<?php

declare(strict_types=1);

namespace App\Actions\Web\Contact;

use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\ContactSubmissionReceived;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class CreateContactSubmissionAction
{
    /**
     * @param  array{name: string, email: string, website_url?: string|null, message: string}  $data
     */
    public function execute(array $data): Submission
    {
        $submission = DB::transaction(function () use ($data): Submission {
            return Submission::create([
                'type' => SubmissionType::Contact,
                'status' => SubmissionStatus::New,
                'locale' => app()->getLocale(),
                'first_name' => $data['name'],
                'email' => $data['email'],
                'website_url' => ($data['website_url'] ?? '') !== '' ? $data['website_url'] : null,
                'message' => $data['message'],
            ]);
        });

        // Effet de bord apres commit : notification synchrone a Festilaw (pas de file/worker).
        Mail::to(config('festilaw.notification_email'))
            ->send(new ContactSubmissionReceived($submission));

        return $submission;
    }
}
