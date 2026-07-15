<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the STARTER mandate to a clean, per-customer PDF (submission data merged into the Blade
 * template, with the signing partner's signature tag baked in). Swapping the real contract later =
 * editing the Blade template content; the variables and signature tag stay.
 */
final readonly class ContractPdfGenerator
{
    /** @return string  Raw PDF bytes. */
    public function generate(Submission $submission): string
    {
        return Pdf::loadView('contracts.starter-mandate', [
            'submission' => $submission,
            'date' => now()->format('d F Y'),
        ])->output();
    }
}
