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
            'topic_scan' => __('Recherche de pistes'),
            'topic' => __('Choix du sujet'),
            'research_notes' => __('Recherche sur le web'),
            'research' => __('Dossier de faits'),
            'writing' => __('Rédaction'),
            'fact_search' => __('Recherche des preuves'),
            'verification' => __('Vérification'),
            'style' => __('Relecture du style'),
            'translation' => __('Traduction'),
            'translation_fr' => __('Version française'),
            'translation_es' => __('Version espagnole'),
            'version_check_fr' => __('Contrôle de la version française'),
            'version_check_es' => __('Contrôle de la version espagnole'),
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
            $reason === BlogGenerationOutcomeReason::AiModelUnavailable && $step !== null => __('Modèle retiré par le fournisseur d\'IA, à l\'étape « :step »', ['step' => $step]),
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
            BlogGenerationOutcomeReason::SafetyStop => __('Aucun article n\'a été créé. La génération s\'est arrêtée par sécurité, car son coût allait dépasser le double du coût annoncé au lancement. Vous pouvez en lancer une autre. Si l\'arrêt se répète, contactez l\'assistance technique.'),
            BlogGenerationOutcomeReason::SafetyStopBeforeCall => __('Aucun article n\'a été créé. La génération s\'est arrêtée par sécurité avant un appel à l\'IA, car cet appel aurait pu dépasser le double du coût annoncé au lancement. Vous pouvez en lancer une autre, avec un niveau d\'effort plus bas si l\'arrêt se répète.'),
            BlogGenerationOutcomeReason::AiKeyRejected => __('Aucun article n\'a été créé. OpenRouter refuse la clé du site. Collez une nouvelle clé dans les réglages, puis relancez la génération.'),
            BlogGenerationOutcomeReason::AiModelUnavailable => __('Aucun article n\'a été créé. Le fournisseur d\'IA ne propose plus le modèle choisi pour cette étape. Choisissez-en un autre dans les réglages, ou cliquez sur « Réinitialiser » sous les listes, enregistrez, puis relancez la génération. Elle reprendra à l\'étape qui a échoué.'),
            default => __('Aucun article n\'a été créé.'),
        };
    }
}
