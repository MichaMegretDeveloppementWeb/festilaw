<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;

/**
 * Une etape editoriale ne peut pas continuer : le modele a rendu une reponse inutilisable (JSON rate, champ
 * manquant, repere perdu), ou ce dont l'etape a besoin manque. Le moteur reessaie l'etape, puis la met en
 * echec ; la cause technique ne va qu'au journal du serveur (decision Q31).
 */
final class BlogEditorialException extends BaseAppException
{
    public static function unusableAnswer(string $step, string $reason): self
    {
        return new self(
            technicalMessage: "Blog generation step [{$step}] got an unusable answer from the model: {$reason}",
            userMessage: __('L\'IA a rendu une réponse inutilisable.'),
        );
    }

    public static function missingInput(string $step, string $what): self
    {
        return new self(
            technicalMessage: "Blog generation step [{$step}] is missing its input: {$what}",
            userMessage: __('Une étape précédente n\'a pas rendu ce qu\'il fallait.'),
        );
    }

    public static function imageSourceFailed(string $reason): self
    {
        return new self(
            technicalMessage: "Blog image source failed: {$reason}",
            userMessage: __('La banque d\'images n\'a pas répondu.'),
        );
    }

    public static function noModel(string $step, string $setting): self
    {
        return new self(
            technicalMessage: "No model is set for blog generation step [{$step}] (setting [{$setting}]).",
            userMessage: __('Aucun modèle n\'est réglé pour cette étape.'),
        );
    }
}
