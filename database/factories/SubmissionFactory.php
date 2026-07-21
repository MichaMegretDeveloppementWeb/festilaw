<?php

namespace Database\Factories;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SubmissionType::Contact,
            'status' => SubmissionStatus::New,
            'locale' => 'en',
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'message' => fake()->sentence(),
        ];
    }

    public function starter(): static
    {
        return $this->state(fn (): array => [
            'type' => SubmissionType::Starter,
            'status' => SubmissionStatus::InProgress,
            'company_name' => fake()->company(),
            'last_name' => fake()->lastName(),
            'website_url' => 'https://'.fake()->domainName(),
            'message' => null,
            'resume_token' => Str::random(48),
            'resume_expires_at' => now()->addDays(30),
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (): array => [
            'type' => SubmissionType::Pro,
            'company_name' => fake()->company(),
            'message' => null,
        ]);
    }

    public function scale(): static
    {
        return $this->state(fn (): array => [
            'type' => SubmissionType::Scale,
            'company_name' => fake()->company(),
            'message' => null,
        ]);
    }

    /**
     * Dossier actif (souscription payee) : un vrai client complet. Un dossier paye a FORCEMENT complete sa
     * mise en place initiale (l'annee 1 l'exige) -> on cree donc aussi le mandat SIGNE et les documents
     * requis, en plus du paiement reussi et du statut « Payé ». Sinon les scenarios (renouvellement,
     * « mon projet ») partent d'une donnee incoherente. Le lien de reprise ne doit plus expirer.
     */
    public function paid(int $serviceYear = 2026): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Paid,
            'resume_expires_at' => null,
        ])->afterCreating(function (Submission $submission) use ($serviceYear): void {
            $submission->payments()->create([
                'type' => PaymentType::StarterSubscription,
                'amount_cents' => 33300,
                'service_year' => $serviceYear,
                'currency' => 'EUR',
                'provider' => 'fake',
                'provider_reference' => 'fake_ref_'.Str::random(8),
                'status' => PaymentStatus::Succeeded,
                'paid_at' => now(),
            ]);

            $submission->contract()->create([
                'filled_fields' => [],
                'signature_status' => SignatureStatus::Signed,
                'signature_provider' => 'fake',
                'signature_provider_reference' => 'fake_doc_'.Str::random(8),
                'signed_file_path' => 'contracts/fake_'.Str::random(8).'.pdf',
                'signed_at' => now(),
            ]);

            foreach ([DocumentType::TurnoverProof, DocumentType::TechnicalDocumentation] as $type) {
                $submission->uploadedDocuments()->create([
                    'type' => $type,
                    'file_path' => 'starter-documents/'.$submission->id.'/'.Str::random(8).'.pdf',
                    'original_filename' => 'document.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 12345,
                    'uploaded_at' => now(),
                ]);
            }
        });
    }
}
