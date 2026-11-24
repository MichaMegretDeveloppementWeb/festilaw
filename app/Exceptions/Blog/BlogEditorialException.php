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

    public static function cutOff(string $step, int $maxTokens): self
    {
        return new self(
            technicalMessage: "Blog generation step [{$step}] got an answer cut off at its limit of {$maxTokens} tokens (finish reason: length).",
            userMessage: __('L\'IA a rendu une réponse incomplète.'),
        );
    }

    /** Le SDK rend vide, sans rien lever, une sortie structuree qui n'est pas du JSON lisible. */
    public static function notJson(string $step): self
    {
        return new self(
            technicalMessage: "Blog generation step [{$step}] got an answer that is not valid JSON for its schema.",
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

    public static function nothingToTranslate(string $from): self
    {
        return new self(
            technicalMessage: "Assisted translation refused: the [{$from}] version has no title and no text.",
            userMessage: __('La langue de départ n\'a ni titre ni texte à traduire.'),
        );
    }

    public static function nothingToTranslateInField(string $from, string $field): self
    {
        return (new self(
            technicalMessage: "Assisted translation refused: the [{$field}] field of the [{$from}] version is empty.",
            userMessage: __('Ce champ est vide dans la langue de départ.'),
        ))->asRefusal();
    }

    public static function unsupportedTranslation(string $from, string $to): self
    {
        return new self(
            technicalMessage: "Assisted translation refused from [{$from}] to [{$to}].",
            userMessage: __('Cette traduction n\'est pas possible.'),
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
