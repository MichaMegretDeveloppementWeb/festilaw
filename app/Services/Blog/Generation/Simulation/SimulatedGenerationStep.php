<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Simulation;

use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Etape factice : elle attend quelques secondes et rend une consommation fictive, sans appeler aucun modele
 * ni rien ecrire. Sert a essayer le moteur, la reprise, les plafonds et les ecrans sans depenser un centime.
 */
final readonly class SimulatedGenerationStep implements GenerationStep
{
    /** Le reglage « modele par etape » dont depend chaque etape factice (aucun pour l'assemblage). */
    private const MODEL_SETTING = [
        'topic' => 'topic',
        'research' => 'research',
        'writing' => 'writing',
        'verification' => 'verification',
        'style' => 'writing',
        'translation' => 'translation',
        'translation_fr' => 'translation',
        'translation_es' => 'translation',
        'images' => 'image',
    ];

    /**
     * Compte rendu factice de chaque etape, pour que l'ecran d'une generation se regarde tel qu'il sera
     * avec de vraies etapes. Chaque phrase dit qu'elle est factice.
     */
    private const SUMMARY = [
        'topic' => 'Compte rendu factice. J\'ai retenu ce sujet parce qu\'il revient souvent dans les questions des vendeurs et qu\'aucun article du blog ne le traite encore.',
        'research' => 'Compte rendu factice. J\'ai consulté le texte du règlement sur EUR-Lex et les pages d\'aide de deux places de marché.',
        'writing' => 'Compte rendu factice. L\'article part d\'un cas concret, puis explique la règle et ce que le vendeur doit préparer.',
        'verification' => 'Compte rendu factice. Chaque affirmation a été rapprochée de sa source, et deux phrases sans source ont été retirées.',
        'style' => 'Compte rendu factice. Les tournures d\'un texte généré ont été reprises, sans toucher aux faits.',
        'translation_fr' => 'Compte rendu factice. Traduction en français, avec les termes du règlement dans leur version officielle.',
        'translation_es' => 'Compte rendu factice. Traduction en espagnol, avec les termes du règlement dans leur version officielle.',
        'images' => 'Compte rendu factice. Une image principale a été choisie pour illustrer le sujet.',
    ];

    public function __construct(
        private string $key,
        private int $seconds,
        private int $costMicrousd,
        private bool $fails,
        private bool $stopsWithoutTopic,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function timeoutSeconds(): int
    {
        return $this->seconds + 30;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        Sleep::for($this->seconds)->seconds();

        if ($this->fails) {
            throw new RuntimeException("Simulated failure of step [{$this->key}] (attempt {$context->attempt}).");
        }

        if ($this->stopsWithoutTopic) {
            return GenerationStepResult::stop(BlogGenerationOutcomeReason::NoRelevantTopic, ['simulated' => true]);
        }

        $modelSetting = self::MODEL_SETTING[$this->key] ?? null;

        return GenerationStepResult::continue(
            $this->key === 'translation' ? $this->markedTranslation($context) : ['simulated' => true, 'step' => $this->key],
            $modelSetting === null ? GenerationUsage::none() : new GenerationUsage(
                model: $context->settings->modelFor($modelSetting),
                inputTokens: 1_500,
                outputTokens: 600,
                costMicrousd: $this->costMicrousd,
                costIsEstimated: true,
            ),
            self::SUMMARY[$this->key] ?? null,
        );
    }

    /**
     * Traduction assistee factice : le texte de depart, titre marque de la langue visee, pour essayer le
     * formulaire en local sans appeler de modele.
     *
     * @return array<string, mixed>
     */
    private function markedTranslation(GenerationStepContext $context): array
    {
        $request = $context->payload['translation'] ?? [];
        $article = is_array($request['article'] ?? null) ? $request['article'] : [];
        $to = (string) ($request['to'] ?? '');

        return [
            'simulated' => true,
            'locale' => $to,
            'article' => [...$article, 'title' => '['.strtoupper($to).'] '.($article['title'] ?? '')],
        ];
    }
}
