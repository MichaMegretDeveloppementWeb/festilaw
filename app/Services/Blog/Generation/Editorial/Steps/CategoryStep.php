<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\CategoryMatchingAgent;
use App\Ai\Agents\Blog\CategoryNamingAgent;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\Blog\BlogEditorialException;
use App\Repositories\Blog\BlogCategoryFilingRepository;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;

/**
 * Etape « choix de la categorie », en deux temps, une fois l'article anglais termine (proposition de Micha du
 * 27/11). La redaction choisissait la categorie en voyant la liste des categories existantes : elle rangeait
 * tout dans la premiere creee, et ne reprenait une categorie que sous le meme nom exact.
 *
 * D'abord, le modele nomme le sujet principal de l'article sans connaitre les categories du blog, d'apres son
 * titre, son extrait et ses intertitres. Ensuite, un second appel compare ce nom aux categories existantes,
 * chacune avec les titres de ses derniers articles, et ne reprend que celle qui traite le meme sujet. Sans
 * categorie reprise, le nom propose devient une categorie nouvelle, creee avec le brouillon par l'assemblage.
 * Le second appel n'a pas lieu quand le blog n'a aucune categorie, ou quand une categorie porte deja ce nom.
 *
 * Au-dela de `maxCategories` categories, la creation est fermee, pour que le menu du blog ne s'emiette pas : la
 * comparaison choisit alors la plus proche. Un sujet que le modele ne sait pas nommer laisse l'article sans
 * categorie, que Laetitia choisit avant de publier.
 */
final class CategoryStep extends EditorialStep
{
    /** Le nom d'une categorie proposee : quelques mots, borne a cette longueur. */
    private const MAX_NAME = 60;

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogCategoryFilingRepository $blog,
        private readonly int $maxCategories = 12,
        private readonly int $titlesPerCategory = 3,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'category';
    }

    public function timeoutSeconds(): int
    {
        return 420;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $writing = $context->output('writing');

        // Une generation redigee avant cette etape avait sa categorie des la redaction, et sa version francaise a pu
        // deja traduire le nom propose : elle la garde.
        if (array_key_exists('category_id', $writing) || array_key_exists('new_category', $writing)) {
            return $this->decided(
                is_int($writing['category_id'] ?? null) ? $writing['category_id'] : null,
                is_string($writing['new_category'] ?? null) && $writing['new_category'] !== '' ? $writing['new_category'] : null,
                null,
                [],
                GenerationUsage::none(),
                __('La catégorie a été choisie pendant la rédaction, comme pour toute génération lancée avant que ce choix ait sa propre étape.'),
            );
        }

        $article = EditorialArticle::fromArray($context->output('style')['article'] ?? []);

        if ($article->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the English article');
        }

        $naming = $this->ask($context, new CategoryNamingAgent($this->prompts->get('step-category-naming')), $this->block('Article', [
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'headings' => EditorialMarkdown::headings($article->body),
        ]));
        $usage = $naming->usage;
        $proposed = $this->cleanName($this->text($naming->structured(), 'name'));
        $reasons = ['naming' => $this->text($naming->structured(), 'reason')];

        if ($proposed === '') {
            return $this->decided(null, null, null, $reasons, $usage, __('L\'IA n\'a pas su nommer le sujet de l\'article. Aucune catégorie n\'a été choisie, et vous en choisirez une avant de publier.'));
        }

        $categories = $this->blog->categoriesWithTitles($this->titlesPerCategory);
        $canCreate = count($categories) < $this->maxCategories;
        $named = __('L\'IA a nommé le sujet de l\'article « :name », sans voir les catégories du blog.', ['name' => $proposed]);

        foreach ($categories as $category) {
            if (mb_strtolower($category['name']) === mb_strtolower($proposed)) {
                return $this->decided($category['id'], null, $proposed, $reasons, $usage, $named.' '.__('La catégorie « :name » porte déjà ce nom en anglais, et l\'article y est rangé.', ['name' => $category['name_fr']]));
            }
        }

        if ($categories === []) {
            return $canCreate
                ? $this->decided(null, $proposed, $proposed, $reasons, $usage, $named.' '.__('Le blog n\'a encore aucune catégorie. Celle-ci sera créée avec le brouillon, nommée dans les trois langues.'))
                : $this->decided(null, null, $proposed, $reasons, $usage, $named.' '.__('Aucune catégorie ne peut être créée, et vous en choisirez une avant de publier.'));
        }

        $matching = $this->ask($context, new CategoryMatchingAgent($this->prompts->get('step-category-matching')), spent: $usage, prompt: implode("\n\n", [
            $this->block('Article', ['title' => $article->title, 'excerpt' => $article->excerpt]),
            $this->block('Proposed category', $proposed),
            $this->block('Categories', [
                ...array_map(static fn (array $category): array => ['id' => $category['id'], 'name' => $category['name'], 'latest_articles' => $category['titles']], $categories),
                ...($canCreate ? [] : ['No new category can be created: give the id of the closest category.']),
            ]),
        ]));
        $usage = $usage->plus($matching->usage);
        $reasons['matching'] = $this->text($matching->structured(), 'reason');
        $chosenId = $matching->structured()['category_id'] ?? null;
        $chosen = array_values(array_filter($categories, static fn (array $category): bool => $category['id'] === $chosenId))[0] ?? null;

        return match (true) {
            $chosen !== null && $canCreate => $this->decided($chosen['id'], null, $proposed, $reasons, $usage, $named.' '.__('La catégorie « :name » traite le même sujet, et l\'article y est rangé.', ['name' => $chosen['name_fr']])),
            $chosen !== null => $this->decided($chosen['id'], null, $proposed, $reasons, $usage, $named.' '.__('Le blog a atteint son nombre maximal de catégories, et l\'article est rangé dans la plus proche, « :name ».', ['name' => $chosen['name_fr']])),
            $canCreate => $this->decided(null, $proposed, $proposed, $reasons, $usage, $named.' '.__('Aucune catégorie existante ne traite ce sujet. Celle-ci sera créée avec le brouillon, nommée dans les trois langues.')),
            default => $this->decided(null, null, $proposed, $reasons, $usage, $named.' '.__('Aucune catégorie ne convient et le blog a atteint son nombre maximal de catégories. Vous en choisirez une avant de publier.')),
        };
    }

    /**
     * La decision de l'etape, lue par les traductions (nom d'une categorie nouvelle) et par l'assemblage.
     *
     * @param  array<string, string>  $reasons  pourquoi, en anglais, selon chaque appel
     */
    private function decided(?int $categoryId, ?string $newCategory, ?string $proposed, array $reasons, GenerationUsage $usage, string $summary): GenerationStepResult
    {
        return GenerationStepResult::continue(
            ['category_id' => $categoryId, 'new_category' => $newCategory, 'proposed_name' => $proposed, 'reasons' => $reasons],
            $usage,
            $summary,
        );
    }

    /** Le nom propose, sans guillemets ni ponctuation autour, borne, avec une majuscule initiale. */
    private function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), " \t\"'.,;:!?«»“”");

        if ($name === '') {
            return '';
        }

        $name = mb_strlen($name) > self::MAX_NAME ? rtrim(mb_substr($name, 0, self::MAX_NAME)) : $name;

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }
}
