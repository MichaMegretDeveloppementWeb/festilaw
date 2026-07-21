<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Donnees de demonstration pour tester les renouvellements ("simuler comme si on etait en janvier").
 * Cree des dossiers PAYES actifs : deux qui doivent renouveler cette annee (Creator + Pro, payes l'an
 * dernier) et un a jour (paye cette annee, temoin). Re-executable : purge d'abord les dossiers de demo.
 *
 * Utilisation :
 *   php artisan db:seed --class=RenewalDemoSeeder
 *   php artisan festilaw:process-renewals --now=AAAA-01-05   (rappels + digest "a renouveler")
 *   php artisan festilaw:process-renewals --now=AAAA-02-10   (digest "en retard")
 */
final class RenewalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Purge des dossiers de demo precedents (cascade sur contrat/paiements).
        Submission::query()->where('meta->demo', 'renewal')->get()->each->delete();

        $thisYear = (int) now()->year;

        $this->makePaidDossier('Northwind Goods (demo)', 'northwind-demo@festilaw.test', SubmissionType::Starter, $thisYear - 1);
        $this->makePaidDossier('Alpine Gear (demo)', 'alpine-demo@festilaw.test', SubmissionType::Pro, $thisYear - 1);
        $this->makePaidDossier('Studio Lumen (demo, a jour)', 'lumen-demo@festilaw.test', SubmissionType::Starter, $thisYear);

        $this->command?->info('Dossiers de demo renouvellement crees. Simulez avec : php artisan festilaw:process-renewals --now='.$thisYear.'-01-05');
    }

    private function makePaidDossier(string $company, string $email, SubmissionType $type, int $serviceYear): void
    {
        $signedAt = Carbon::create($serviceYear, 1, 15);

        $submission = Submission::create([
            'type' => $type,
            'status' => SubmissionStatus::Paid,
            'locale' => 'fr',
            'company_name' => $company,
            'first_name' => 'Client',
            'last_name' => 'Demo',
            'email' => $email,
            'resume_token' => Str::random(48),
            'resume_expires_at' => null,
            'eu_rp_address' => "Festilaw B.V.\nExample street 1\n1000 Amsterdam, NL",
            'meta' => ['demo' => 'renewal'],
        ]);

        $submission->contract()->create([
            'signature_status' => SignatureStatus::Signed,
            'signed_at' => $signedAt,
            'signed_file_path' => 'contracts/demo-mandate.pdf',
            'filled_fields' => ['incorporation_place' => 'Amsterdam, NL', 'founding_year' => '2018', 'activity' => 'e-commerce'],
        ]);

        $submission->payments()->create([
            'type' => PaymentType::StarterSubscription,
            'amount_cents' => $type->annualCents(),
            'service_year' => $serviceYear,
            'currency' => 'EUR',
            'provider' => 'fake',
            'provider_reference' => 'demo_'.Str::random(10),
            'status' => PaymentStatus::Succeeded,
            'paid_at' => $signedAt,
        ]);
    }
}
