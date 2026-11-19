<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Enums\Blog\Generation\BlogEffortLevel;

/**
 * Ce que chaque niveau d'effort change a chaque etape : l'effort de reflexion demande au modele et le nombre
 * de recherches web. Les niveaux ne s'ecrivent qu'a un endroit (`blog.ai.effort.levels`), que lisent l'appel
 * au modele (BlogAgentCaller), les etapes qui cherchent sur le web et l'estimation du cout : un niveau ne peut
 * donc pas couter autre chose que ce qu'il annonce. Calcul pur, aucune requete.
 */
final readonly class BlogEffortLevels
{
    /** @param  array<string, array{reasoning: array<string, string>, searches: array<string, int>}>  $levels */
    public function __construct(
        private array $levels,
        private BlogAiModelCatalog $models,
    ) {}

    /**
     * L'effort de reflexion a demander pour une etape (`none`, `low`, `medium`...). Un modele qui refuse
     * l'absence de reflexion recoit le plus petit effort qu'il accepte (`lowest_effort` de sa fiche).
     */
    public function reasoning(BlogEffortLevel $level, string $step, ?string $model = null): string
    {
        $efforts = $this->levels[$level->value]['reasoning'] ?? [];
        $effort = (string) ($efforts[$step] ?? $efforts['default'] ?? 'low');

        return $effort === 'none' ? ($this->models->lowestEffort($model) ?? $effort) : $effort;
    }

    /** Le nombre de recherches web d'une etape qui en fait (recherche de pistes, recherche sur le web). */
    public function searches(BlogEffortLevel $level, string $step): int
    {
        return (int) ($this->levels[$level->value]['searches'][$step] ?? 0);
    }
}
