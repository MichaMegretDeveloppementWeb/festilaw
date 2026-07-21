<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the Responsible Person Service Agreement to a per-customer PDF (main agreement + shared
 * General Terms annex), in the dossier's language (en/fr/es). The pack (Creator/Pro) drives the title
 * and the annual fee; the client-specific fields come from the contract's filled_fields and are
 * emphasised so they stand out. Invisible SignWell text tags on the signing line let the signature and
 * date fields land directly on the contract page.
 */
final readonly class ContractPdfGenerator
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'es'];

    /** @return string  Raw PDF bytes. */
    public function generate(Submission $submission): string
    {
        $isPro = $submission->type === SubmissionType::Pro;
        $locale = in_array($submission->locale, self::SUPPORTED_LOCALES, true) ? $submission->locale : 'en';

        $feeEuros = intdiv(
            $isPro
                ? (int) config('festilaw.pro.amount_cents', 120000)
                : (int) config('festilaw.starter.amount_cents', 33300),
            100,
        );

        /** @var array<string, mixed> $fields */
        $fields = $submission->contract?->filled_fields ?? [];

        return Pdf::loadView("contracts.{$locale}.agreement", [
            'logo' => $this->logoDataUri(),
            'pack' => $isPro ? 'Pro' : 'Creator',
            'fee' => $feeEuros,
            'feeWords' => $this->spellFee($feeEuros, $locale),
            'date' => now()->locale($locale)->isoFormat('LL'),
            'reference' => (string) $submission->reference,
            'company' => $this->emphasise($submission->company_name ?: '-'),
            'place' => $this->emphasise((string) ($fields['incorporation_place'] ?? '-')),
            'year' => $this->emphasise((string) ($fields['founding_year'] ?? '-')),
            'activity' => $this->emphasise((string) ($fields['activity'] ?? '-')),
            'signer' => $this->signerName($submission),
        ])->output();
    }

    /** Bold + italic HTML so a client-provided value stands out in the contract body. */
    private function emphasise(string $value): string
    {
        return '<strong><em>'.e($value).'</em></strong>';
    }

    /** The Festilaw logo as a base64 data URI (reliable in dompdf, no file-path/chroot concerns). */
    private function logoDataUri(): string
    {
        $path = public_path('logo-festilaw.jpg');

        return is_file($path)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path))
            : '';
    }

    private function signerName(Submission $submission): string
    {
        $name = trim(($submission->first_name ?? '').' '.($submission->last_name ?? ''));

        return $name !== '' ? $name : ($submission->company_name ?: '-');
    }

    /** Spells the annual fee for the two known packs, per language; falls back to the figure otherwise. */
    private function spellFee(int $euros, string $locale): string
    {
        $words = [
            'en' => [333 => 'three hundred and thirty-three euros', 1200 => 'one thousand two hundred euros'],
            'fr' => [333 => 'trois cent trente-trois euros', 1200 => 'mille deux cents euros'],
            'es' => [333 => 'trescientos treinta y tres euros', 1200 => 'mil doscientos euros'],
        ];

        return $words[$locale][$euros] ?? $euros.' EUR';
    }
}
