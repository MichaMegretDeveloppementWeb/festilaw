<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the Responsible Person Service Agreement to a per-customer PDF (main agreement + shared
 * General Terms annex). The pack (Creator/Pro) drives the title and the annual fee; the client-specific
 * fields come from the contract's filled_fields. The signing partner appends its own signature page.
 */
final readonly class ContractPdfGenerator
{
    /** @return string  Raw PDF bytes. */
    public function generate(Submission $submission): string
    {
        $isPro = $submission->type === SubmissionType::Pro;

        $feeEuros = intdiv(
            $isPro
                ? (int) config('festilaw.pro.amount_cents', 120000)
                : (int) config('festilaw.starter.amount_cents', 33300),
            100,
        );

        /** @var array<string, mixed> $fields */
        $fields = $submission->contract?->filled_fields ?? [];

        return Pdf::loadView('contracts.responsible-person-agreement', [
            'submission' => $submission,
            'date' => now()->format('d F Y'),
            'pack' => $isPro ? 'Pro' : 'Creator',
            'fee' => $feeEuros,
            'feeWords' => $this->spellFee($feeEuros),
            'company' => $submission->company_name ?: '—',
            'place' => $fields['incorporation_place'] ?? '—',
            'year' => $fields['founding_year'] ?? '—',
            'activity' => $fields['activity'] ?? '—',
        ])->output();
    }

    /** Spells the annual fee for the two known packs; falls back to the figure for any other amount. */
    private function spellFee(int $euros): string
    {
        return match ($euros) {
            333 => 'three hundred and thirty-three euros',
            1200 => 'one thousand two hundred euros',
            default => number_format($euros).' euros',
        };
    }
}
