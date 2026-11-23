<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\BaseAppException;

/**
 * Une etape a perdu le verrou de sa generation entre deux appels a l'IA : un autre worker l'a reprise apres une
 * etape plus longue que prevu. Elle s'arrete avant l'appel suivant, qui serait paye pour rien (audit du 23/11) ;
 * le moteur rend la main, et ce qui a deja ete depense dans cet essai reste compte.
 */
final class BlogGenerationLockLostException extends BaseAppException
{
    public function __construct(string $step, public readonly GenerationUsage $usage)
    {
        parent::__construct(
            technicalMessage: "Blog generation step [{$step}] lost its lock before an AI call.",
            userMessage: __('Cette génération a été reprise par un autre traitement.'),
        );
    }
}
