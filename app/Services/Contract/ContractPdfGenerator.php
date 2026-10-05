<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Submission\SubmissionType;
use App\Models\Contract;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the Responsible Person Service Agreement to a per-customer PDF (main agreement + shared
 * General Terms annex), in the dossier's language (en/fr/es). The pack (Creator/Pro) drives the title
 * and the annual fee; the client-specific fields come from the contract's filled_fields and are
 * emphasised so they stand out. Invisible SignWell text tags on the signing line let the signature and
 * date fields land directly on the contract page. Also renders blank specimens (bracketed placeholders)
 * that Festilaw can keep or share.
 */
final readonly class ContractPdfGenerator
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'es'];

    /** Placeholders of a blank specimen, per language, in place of the client's details. */
    private const SPECIMEN_PLACEHOLDERS = [
        'en' => ['reference' => 'SPECIMEN', 'company' => '[Client company name]', 'place' => '[city, country]', 'year' => '[year]', 'activity' => '[activity]', 'signer' => '[Signatory name]'],
        'fr' => ['reference' => 'MODÈLE', 'company' => '[Nom de la société cliente]', 'place' => '[ville, pays]', 'year' => '[année]', 'activity' => '[activité]', 'signer' => '[Nom du signataire]'],
        'es' => ['reference' => 'MODELO', 'company' => '[Nombre de la empresa cliente]', 'place' => '[ciudad, país]', 'year' => '[año]', 'activity' => '[actividad]', 'signer' => '[Nombre del firmante]'],
    ];

    /**
     * The mandate PDF of the dossier: its current mandate by default, or the given one (e.g. the Pro mandate
     * of a pack upgrade, signed while the dossier is still Creator). The pack is the mandate's own.
     *
     * @return string Raw PDF bytes.
     */
    public function generate(Submission $submission, ?Contract $contract = null): string
    {
        return $this->render($this->supportedLocale($submission->locale), $this->agreementData($submission, $contract));
    }

    /**
     * View data of the dossier's mandate (its current one by default, or the given one): the mandate's pack
     * and fee, and the client's details.
     *
     * @return array<string, mixed>
     */
    public function agreementData(Submission $submission, ?Contract $contract = null): array
    {
        $locale = $this->supportedLocale($submission->locale);
        $contract ??= $submission->contract;
        $pack = $contract?->pack ?? $submission->type;

        /** @var array<string, mixed> $fields */
        $fields = $contract?->filled_fields ?? [];

        return $this->commonData($pack, $locale) + [
            'reference' => (string) $submission->reference,
            'company' => $this->emphasise($submission->company_name ?: '-'),
            'place' => $this->emphasise((string) ($fields['incorporation_place'] ?? '-')),
            'year' => $this->emphasise((string) ($fields['founding_year'] ?? '-')),
            'activity' => $this->emphasise((string) ($fields['activity'] ?? '-')),
            'signer' => $this->signerName($submission),
        ];
    }

    /** @return string  Raw PDF bytes of a blank agreement (specimen) for the pack, in the given language. */
    public function specimen(SubmissionType $type, string $locale): string
    {
        $locale = $this->supportedLocale($locale);

        return $this->render($locale, $this->specimenData($type, $locale));
    }

    /**
     * View data of a blank agreement: bracketed placeholders instead of the client's details.
     *
     * @return array<string, mixed>
     */
    public function specimenData(SubmissionType $type, string $locale): array
    {
        $locale = $this->supportedLocale($locale);
        $placeholders = self::SPECIMEN_PLACEHOLDERS[$locale];

        return $this->commonData($type, $locale) + [
            'reference' => $placeholders['reference'],
            'company' => $this->emphasise($placeholders['company']),
            'place' => $this->emphasise($placeholders['place']),
            'year' => $this->emphasise($placeholders['year']),
            'activity' => $this->emphasise($placeholders['activity']),
            'signer' => $placeholders['signer'],
        ];
    }

    /**
     * What every agreement carries whoever the client: logo, pack, annual fee (figures and words), date.
     *
     * @return array{logo: string, pack: string, fee: int, feeWords: string, date: string}
     */
    private function commonData(SubmissionType $type, string $locale): array
    {
        $feeEuros = intdiv($type->annualCents(), 100);

        return [
            'logo' => $this->logoDataUri(),
            'pack' => $type === SubmissionType::Pro ? 'Pro' : 'Creator',
            'fee' => $feeEuros,
            'feeWords' => $this->spellFee($feeEuros, $locale),
            'date' => now()->locale($locale)->isoFormat('LL'),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function render(string $locale, array $data): string
    {
        return Pdf::loadView("contracts.{$locale}.agreement", $data)->output();
    }

    private function supportedLocale(?string $locale): string
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'en';
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
