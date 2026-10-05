<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Submission\SubmissionType;
use App\Services\Contract\ContractPdfGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Modeles vierges du mandat (Pack Creator et Pack Pro, en anglais, francais et espagnol) : le contrat tel que
 * le signent les clients, champs client remplaces par des reperes entre crochets. A relancer apres chaque
 * evolution des gabarits de contrat (resources/views/contracts/) pour en transmettre la version a jour a
 * Festilaw. Fichiers ecrits sur le disque local (prive), jamais exposes par le site.
 */
final class GenerateContractSpecimens extends Command
{
    private const DIRECTORY = 'contract-specimens';

    private const LOCALES = ['en', 'fr', 'es'];

    protected $signature = 'festilaw:contract-specimens';

    protected $description = 'Genere les modeles PDF vierges du mandat (Creator et Pro, EN/FR/ES).';

    public function __construct(private readonly ContractPdfGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $disk = Storage::disk('local');

        foreach ([SubmissionType::Starter, SubmissionType::Pro] as $type) {
            $pack = $type === SubmissionType::Pro ? 'pro' : 'creator';

            foreach (self::LOCALES as $locale) {
                $path = self::DIRECTORY."/festilaw-contract-{$pack}-{$locale}.pdf";
                $disk->put($path, $this->generator->specimen($type, $locale));
                $this->line($disk->path($path));
            }
        }

        $this->info('Modeles du mandat generes (6 PDF).');

        return self::SUCCESS;
    }
}
