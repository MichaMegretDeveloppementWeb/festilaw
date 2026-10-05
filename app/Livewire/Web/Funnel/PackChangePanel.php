<?php

declare(strict_types=1);

namespace App\Livewire\Web\Funnel;

use App\Actions\Web\Starter\CancelPackUpgradeAction;
use App\Actions\Web\Starter\MarkContractDeclinedAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Actions\Web\Starter\StartContractSigningAction;
use App\Actions\Web\Starter\StartPackUpgradeAction;
use App\Actions\Web\Starter\StartPackUpgradePaymentAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureEventOutcome;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesUnexpectedErrors;
use App\Models\Contract;
use App\Models\Submission;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Panneau "changer de pack" de l'espace client d'un dossier paye (SC12, page "my project"). Montee vers le
 * Pro : le client l'ouvre, signe son mandat Pro dans une fenetre sur la page (meme mecanique SignWell que le
 * parcours), puis paie la difference au prorata ; la montee s'applique a la confirmation du paiement. Il peut
 * y renoncer tant qu'il n'a pas paye. Toute la logique est dans PackChangeService et les Actions.
 */
final class PackChangePanel extends Component
{
    use HandlesUnexpectedErrors;

    /** Tentatives bornees de confirmation de la signature apres l'evenement "completed" de l'iframe. */
    private const MAX_SIGNATURE_POLLS = 15;

    #[Locked]
    public Submission $submission;

    /** La boite de confirmation "passer au Pro ?" est ouverte. */
    public bool $confirmingUpgrade = false;

    /** L'iframe a signale la signature : on attend la confirmation du prestataire (boucle bornee). */
    public bool $confirmingSignature = false;

    public int $signatureChecks = 0;

    /** Une signature est deja en cours au chargement : verification silencieuse (wire:init). */
    public bool $autoConfirm = false;

    public function mount(Submission $submission, PackChangeService $packChanges): void
    {
        $this->submission = $submission;
        $contract = $packChanges->signableContract($submission);
        $this->autoConfirm = $contract !== null && (string) ($contract->signature_provider_reference ?? '') !== '';
    }

    public function startUpgrade(StartPackUpgradeAction $startUpgrade): void
    {
        try {
            $startUpgrade->execute($this->submission);
        } catch (BaseAppException $e) {
            $this->addError('pack', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'pack', 'pack upgrade start');

            return;
        }

        $this->confirmingUpgrade = false;
    }

    public function cancelUpgrade(CancelPackUpgradeAction $cancelUpgrade): void
    {
        try {
            $cancelUpgrade->execute($this->submission);
        } catch (BaseAppException $e) {
            $this->addError('pack', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'pack', 'pack upgrade cancel');

            return;
        }

        $this->confirmingSignature = false;
        session()->now('pack_status', 'upgrade_cancelled');
    }

    /** Ouvre la signature du mandat Pro sur la page : reprend la session en cours, sinon en cree une. */
    public function sign(PackChangeService $packChanges, StartContractSigningAction $startSigning, SignatureGatewayInterface $signatureGateway): void
    {
        $contract = $packChanges->signableContract($this->submission);
        if ($contract === null) {
            return;
        }

        $existingUrl = $this->existingSigningUrl($contract, $signatureGateway);
        if ($existingUrl !== null) {
            $this->openSigning($existingUrl);

            return;
        }

        try {
            $session = $startSigning->execute($this->submission, $contract);
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('pack', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'pack', 'pack upgrade signing');

            return;
        }

        $this->openSigning($session->signingUrl);
    }

    /** L'iframe a signale la signature : confirmation cote serveur, avec quelques nouvelles tentatives. */
    public function signingCompleted(PackChangeService $packChanges, SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markSigned): void
    {
        $this->confirmingSignature = true;
        $this->signatureChecks = 0;
        $this->pollSignature($packChanges, $signatureGateway, $markSigned);
    }

    public function pollSignature(PackChangeService $packChanges, SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markSigned): void
    {
        if (! $this->confirmingSignature || $this->signatureChecks >= self::MAX_SIGNATURE_POLLS) {
            return;
        }

        $this->signatureChecks++;

        try {
            if ($this->tryConfirmSignature($packChanges, $signatureGateway, $markSigned)) {
                $this->confirmingSignature = false;
            }
        } catch (Throwable $e) {
            Log::error('Pack upgrade signature poll failed.', ['exception' => $e]);
        }
    }

    /** Bouton "J'ai signe, verifier" : interroge le prestataire et avance si c'est signe. */
    public function confirmSignature(PackChangeService $packChanges, SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markSigned): void
    {
        try {
            if (! $this->tryConfirmSignature($packChanges, $signatureGateway, $markSigned)) {
                $this->addError('pack', __('Your signature has not been recorded yet. If you have just signed, wait a few seconds and check again.'));
            }
        } catch (Throwable $e) {
            Log::error('Pack upgrade signature confirmation failed.', ['exception' => $e]);
            $this->addError('pack', __('We could not check your signature right now. Please try again in a moment.'));
        }
    }

    /** Verification silencieuse au chargement ou a la fermeture de la fenetre de signature. */
    public function autoConfirmSignature(PackChangeService $packChanges, SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markSigned): void
    {
        $this->autoConfirm = false;

        try {
            $this->tryConfirmSignature($packChanges, $signatureGateway, $markSigned);
        } catch (Throwable $e) {
            Log::error('Pack upgrade auto signature confirmation failed.', ['exception' => $e]);
        }
    }

    /** Le signataire a refuse dans l'iframe : on l'enregistre d'apres le prestataire (jamais le seul navigateur). */
    public function signingDeclined(PackChangeService $packChanges, SignatureGatewayInterface $signatureGateway, MarkContractDeclinedAction $markDeclined): void
    {
        $this->confirmingSignature = false;
        $contract = $packChanges->signableContract($this->submission);
        if ($contract === null || (string) ($contract->signature_provider_reference ?? '') === '') {
            return;
        }

        try {
            if ($signatureGateway->checkStatus($contract)->outcome === SignatureEventOutcome::Declined) {
                $markDeclined->execute($contract);
            }
        } catch (Throwable $e) {
            Log::error('Pack upgrade signature decline check failed.', ['exception' => $e]);
        }
    }

    public function signingUnavailable(): void
    {
        $this->addError('pack', __('The signing window could not open. Please allow content from SignWell on this page (content blockers can prevent it) and try again.'));
    }

    /** Paiement de la difference : redirection vers la page de paiement du prestataire. */
    public function pay(StartPackUpgradePaymentAction $startPayment, PaymentGatewayRegistry $gateways): void
    {
        try {
            $session = $startPayment->execute($this->submission, (string) array_key_first($gateways->options()));
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('pack', __($e->getUserMessage()));

            return;
        } catch (Throwable $e) {
            $this->reportUnexpectedError($e, 'pack', 'pack upgrade payment');

            return;
        }

        $this->redirect($session->redirectUrl);
    }

    public function render(PackChangeService $packChanges): View
    {
        return view('livewire.web.funnel.pack-change-panel', [
            'panel' => $packChanges->panel($this->submission),
            'signatureTimedOut' => $this->signatureChecks >= self::MAX_SIGNATURE_POLLS,
        ]);
    }

    private function tryConfirmSignature(PackChangeService $packChanges, SignatureGatewayInterface $signatureGateway, MarkContractSignedAction $markSigned): bool
    {
        $contract = $packChanges->signableContract($this->submission);
        if ($contract === null || (string) ($contract->signature_provider_reference ?? '') === '') {
            return false;
        }

        $event = $signatureGateway->checkStatus($contract);
        if (! $event->isSigned()) {
            return false;
        }

        $markSigned->execute($contract, $event->providerReference);
        session()->now('pack_status', 'signed');

        return true;
    }

    private function openSigning(string $signingUrl): void
    {
        $this->confirmingSignature = false;
        $this->signatureChecks = 0;
        $this->dispatch('open-signing', url: $signingUrl);
    }

    private function existingSigningUrl(Contract $contract, SignatureGatewayInterface $signatureGateway): ?string
    {
        if ((string) ($contract->signature_provider_reference ?? '') === '') {
            return null;
        }

        try {
            $url = $signatureGateway->currentSigningUrl($contract);

            return ($url !== null && $url !== '') ? $url : null;
        } catch (Throwable $e) {
            Log::warning('Pack upgrade could not reuse the signing session, creating a new one.', ['exception' => $e]);

            return null;
        }
    }
}
