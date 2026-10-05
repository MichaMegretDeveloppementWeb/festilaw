<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Contract\SignatureStatus;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Starter\StarterException;
use App\Mail\FunnelNotification;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Switches an unpaid self-service dossier to the other online pack (Creator <-> Pro), from the dossier
 * itself (its link is the access). The contract names the pack and its fee, so the dossier is not edited
 * in place: it is cancelled and replaced by a new dossier at the chosen pack, which takes over the common
 * data (identity, company, mandate details) and the uploaded documents (moved, never copied: no duplicate
 * personal files). The mandate is signed again for the new pack. The old link dies at once; the new one
 * is emailed and the visitor is taken straight into the new dossier.
 *
 * Never once a payment has been made or is in progress: the same "checkout" lock as the payment start
 * serialises a pack change and a payment on one dossier. Idempotent: a dossier already replaced returns
 * its replacement (double click).
 */
final readonly class ChangeStarterPackAction
{
    /** Stored statuses of a dossier that can still change pack (before payment). */
    private const CHANGEABLE_STATUSES = [
        SubmissionStatus::InProgress,
        SubmissionStatus::AwaitingDocuments,
        SubmissionStatus::AwaitingPayment,
    ];

    public function __construct(
        private StarterDossierResolver $resolver,
        private TeamNotifier $teamNotifier,
        private SendStarterResumeLinkAction $sendResumeLink,
    ) {}

    public function execute(Submission $current, SubmissionType $target): Submission
    {
        $replacement = Cache::lock('checkout:'.$current->getKey(), 15)->block(10, function () use ($current, $target): Submission {
            $current->refresh();

            // Double clic : le dossier vient d'etre remplace, on renvoie son remplacant.
            if ($current->replaced_by_id !== null && $current->replacedBy !== null) {
                return $current->replacedBy;
            }

            $this->guard($current, $target);

            return DB::transaction(fn (): Submission => $this->replace($current, $target));
        });

        if ($replacement->wasRecentlyCreated) {
            // Effets de bord peripheriques, apres commit : un echec est logue sans casser le changement.
            $this->teamNotifier->notify(new FunnelNotification($replacement, FunnelNotificationReason::PackChanged));
            $this->sendResumeLink->execute($replacement);
        }

        return $replacement->refresh();
    }

    private function guard(Submission $current, SubmissionType $target): void
    {
        if (! $current->type->hasOnlineJourney() || ! $target->hasOnlineJourney() || $current->type === $target) {
            throw StarterException::packChangeNotAllowed($current->id, "no online switch from {$current->type->value} to {$target->value}");
        }

        if (! in_array($current->status, self::CHANGEABLE_STATUSES, true) || $current->isActive()) {
            throw StarterException::packChangeNotAllowed($current->id, "dossier is {$current->status->value}");
        }

        // Paiement en cours (checkout ouvert) ou deja regle : le montant et le contrat sont engages.
        $paymentStarted = $current->payments()
            ->whereIn('status', [...PaymentStatus::confirmable(), PaymentStatus::Succeeded])
            ->exists();
        if ($paymentStarted) {
            throw StarterException::packChangeNotAllowed($current->id, 'a payment is in progress or already made');
        }
    }

    private function replace(Submission $current, SubmissionType $target): Submission
    {
        $replacement = Submission::create([
            'type' => $target,
            'status' => SubmissionStatus::InProgress,
            'locale' => $current->locale,
            'company_name' => $current->company_name,
            'company_registration_number' => $current->company_registration_number,
            'website_url' => $current->website_url,
            'first_name' => $current->first_name,
            'last_name' => $current->last_name,
            'email' => $current->email,
            'phone' => $current->phone,
            'resume_token' => Str::random(48),
            'resume_expires_at' => now()->addDays((int) config('festilaw.starter.resume_ttl_days', 30)),
        ]);

        // Nouveau mandat a signer pour le nouveau pack, pre-rempli des infos deja saisies. Le mandat
        // eventuellement signe pour l'ancien pack reste sur l'ancien dossier (trace).
        $replacement->contract()->create([
            'pack' => $replacement->type,
            'signature_status' => SignatureStatus::Pending,
            'filled_fields' => $current->contract?->filled_fields ?? [],
        ]);

        // Les pieces passent au nouveau dossier (les fichiers ne bougent pas sur le disque).
        $current->uploadedDocuments()->update(['submission_id' => $replacement->getKey()]);

        $replacement->load(['contract', 'uploadedDocuments']);
        $replacement->update(['status' => $this->resolver->workflowStatus($replacement)]);

        // L'ancien dossier est annule et son lien meurt tout de suite (page "lien plus valide").
        $current->update([
            'status' => SubmissionStatus::Cancelled,
            'replaced_by_id' => $replacement->getKey(),
            'resume_expires_at' => now(),
        ]);

        return $replacement;
    }
}
