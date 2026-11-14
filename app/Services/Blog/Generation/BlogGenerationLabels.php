<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;

/**
 * Les mots d'une generation, partages par les ecrans du back-office et par les e-mails : libelle d'une etape,
 * sujet, raison d'une generation sans article (avec l'etape en cause) et ce que la personne peut faire
 * ensuite. Rien de technique ici : la cause brute d'un echec ne va qu'au journal du serveur. Calcul pur,
 * aucune requete.
 */
final readonly class BlogGenerationLabels
{
    /** Libelle d'une etape d'apres sa cle ; une cle inconnue (pipeline futur) s'affiche telle quelle. */
    public function step(string $key): string
    {
        return match ($key) {
            'topic' => __('Choix du sujet'),
            'research' => __('Recherche'),
            'writing' => __('Rédaction'),
            'verification' => __('Vérification'),
            'translation_fr' => __('Traduction en français'),
            'translation_es' => __('Traduction en espagnol'),
            'images' => __('Images'),
            'assembly' => __('Assemblage du brouillon'),
            default => $key,
        };
    }

    /** Le sujet a nommer dans un message : celui que l'IA a retenu, sinon celui qui a ete impose. */
    public function subject(?string $topicTitle, ?string $topicHint): string
    {
        return filled($topicTitle) ? $topicTitle : (filled($topicHint) ? $topicHint : __('Sujet choisi par l\'IA'));
    }

    /**
     * Pourquoi une generation n'a pas donne d'article, avec l'etape en cause quand il y en a une. Nul quand
     * il n'y a rien a dire (generation terminee, en attente, en cours).
     *
     * @param  string|null  $failedStep  cle de l'etape en echec, si une etape a echoue
     */
    public function outcome(?BlogGenerationOutcomeReason $reason, ?string $failedStep): ?string
    {
        if ($reason === null) {
            return null;
        }

        $step = $failedStep === null ? null : $this->step($failedStep);

        return match (true) {
            $reason === BlogGenerationOutcomeReason::StepFailed && $step !== null => __('Échec à l\'étape « :step »', ['step' => $step]),
            $reason === BlogGenerationOutcomeReason::StepFailed => __('La génération a échoué'),
            $reason === BlogGenerationOutcomeReason::CreditExhausted && $step !== null => __('Crédit épuisé chez le fournisseur d\'IA, à l\'étape « :step »', ['step' => $step]),
            default => $reason->label(),
        };
    }

    /** Ce que la personne peut faire apres une generation sans article. Nul quand il n'y a rien a dire. */
    public function advice(?BlogGenerationOutcomeReason $reason): ?string
    {
        return match ($reason) {
            null => null,
            BlogGenerationOutcomeReason::StepFailed => __('Aucun article n\'a été créé. Vous pouvez relancer la génération. Elle reprendra à l\'étape qui a échoué, sans refaire ni repayer les étapes terminées. Si l\'échec se répète, contactez l\'assistance technique.'),
            BlogGenerationOutcomeReason::CreditExhausted => __('Aucun article n\'a été créé. Rechargez le crédit du compte chez le fournisseur d\'IA, puis relancez la génération. Elle reprendra à l\'étape qui a échoué. Si l\'échec se répète, contactez l\'assistance technique.'),
            default => __('Aucun article n\'a été créé.'),
        };
    }
}
