<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\UpdatePackPricingAction;
use App\Enums\Submission\SubmissionType;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Billing\PackPricingService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Back-office : tarifs annuels des packs Creator et Pro, editables sans redeploiement. Prix saisis en
 * euros, stockes en centimes. Sert aussi a tester un paiement de bout en bout a faible montant (ex. 1 EUR)
 * puis a retablir le vrai tarif.
 */
#[Layout('layouts.admin')]
class PricingSettings extends Component
{
    use HandlesAdminErrors;

    /** Tarif annuel Creator, en euros (saisie). */
    public string $creatorPrice = '';

    /** Tarif annuel Pro, en euros (saisie). */
    public string $proPrice = '';

    public function mount(PackPricingService $pricing): void
    {
        $this->creatorPrice = $this->toEuros($pricing->annualCents(SubmissionType::Starter));
        $this->proPrice = $this->toEuros($pricing->annualCents(SubmissionType::Pro));
    }

    public function save(UpdatePackPricingAction $updatePricing): void
    {
        $validated = $this->validate(
            [
                'creatorPrice' => ['required', 'numeric', 'min:1', 'max:100000'],
                'proPrice' => ['required', 'numeric', 'min:1', 'max:100000'],
            ],
            [
                'creatorPrice.required' => __('Le tarif Creator est obligatoire.'),
                'proPrice.required' => __('Le tarif Pro est obligatoire.'),
                'creatorPrice.numeric' => __('Le tarif doit être un montant.'),
                'proPrice.numeric' => __('Le tarif doit être un montant.'),
                'creatorPrice.min' => __('Le tarif doit être d\'au moins 1 €.'),
                'proPrice.min' => __('Le tarif doit être d\'au moins 1 €.'),
            ],
        );

        try {
            $updatePricing->execute([
                SubmissionType::Starter->value => $this->toCents((string) $validated['creatorPrice']),
                SubmissionType::Pro->value => $this->toCents((string) $validated['proPrice']),
            ]);
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update pack pricing');

            return;
        }

        $this->dispatch('admin-toast', message: __('Tarifs mis à jour.'), type: 'success');
    }

    private function toEuros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function toCents(string $euros): int
    {
        return (int) round(((float) $euros) * 100);
    }

    public function render(PackPricingService $pricing): View
    {
        return view('livewire.admin.pricing-settings', [
            'creatorDefault' => $this->toEuros($pricing->defaultCents(SubmissionType::Starter)),
            'proDefault' => $this->toEuros($pricing->defaultCents(SubmissionType::Pro)),
        ])->title(__('Tarifs').' · Festilaw');
    }
}
