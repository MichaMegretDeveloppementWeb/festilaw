<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Data\Blog\Generation\EditorialArticle;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;
use App\Repositories\Blog\BlogEditorialContextRepository;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogFactGuard;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * La verification. L'agent relit l'article contre le dossier, la fiche des regles de l'UE et les preuves
 * trouvees sur le web juste avant (FactSearchStep, 20/11), et retire toute affirmation qu'aucun n'etaye. La
 * fiche l'emporte toujours ; une affirmation que les preuves contredisent est corrigee ou retiree. Il ne doit rien ajouter : une version qui apporte
 * un chiffre, un lien ou un jeton absent du texte de depart, ou qui perd un emplacement d'image, lui est
 * renvoyee une fois avec ce qu'elle ajoute, puis refusee. Un renvoi cible coute moins qu'un nouvel essai a
 * l'aveugle, qui refaisait la meme faute (generation 79 du 18/11, numeros d'articles de la fiche ajoutes
 * trois fois). Les articles du blog que le texte cite lui sont donnes (titre et extrait), pour qu'elle garde la
 * phrase qui les presente (generations 78 a 80). Elle recoit aussi les affirmations du redacteur avec leurs
 * appuis, et controle chaque paire (banc d'essai du 18/11). Ce qui a ete retire ou corrige va a la sortie de
 * l'etape, avec sa raison en francais, pour le journal et le banc d'essai. La page de la generation ne montre
 * que les points d'attention : les rares affirmations gardees sur lesquelles un doute reste (Micha, 19/11).
 */
final class VerificationStep extends EditorialStep
{
    private const MAX_ATTENTION_POINTS = 3;

    public function __construct(
        BlogAgentCaller $caller,
        BlogPromptLibrary $prompts,
        private readonly BlogFactGuard $guard,
        private readonly BlogEditorialContextRepository $blog,
    ) {
        parent::__construct($caller, $prompts);
    }

    public function key(): string
    {
        return 'verification';
    }

    public function timeoutSeconds(): int
    {
        return 400;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $written = EditorialArticle::fromArray($context->output('writing')['article'] ?? []);
        $research = $context->output('research');

        if ($written->isEmpty()) {
            throw BlogEditorialException::missingInput($this->key(), 'the written article');
        }

        $agent = new FactCheckAgent($this->prompts->compose('festilaw-context', 'eu-product-rules', 'editorial-guidelines', 'step-verification'), $context->length?->acceptedMax);
        $linked = $this->blog->publishedPostsAmong(array_values(array_unique(array_map(
            static fn (string $token): int => (int) substr($token, strlen('festilaw:post/')),
            array_filter(EditorialMarkdown::linkTokens($written->body), static fn (string $token): bool => str_starts_with($token, 'festilaw:post/')),
        ))));
        $claims = (array) ($context->output('writing')['claims'] ?? []);
        $evidence = trim((string) ($context->output('fact_search')['notes'] ?? ''));
        $prompt = implode("\n\n", array_filter([
            // La date du jour situe les regles datees de la reference (controle du 20/11).
            $this->block('Today', CarbonImmutable::now()->toDateString()),
            $this->block('Research file', ['sources' => $research['sources'] ?? [], 'facts' => $research['facts'] ?? []]),
            $evidence === '' ? null : $this->block('Web evidence on the statements of the article, searched after writing', $evidence),
            $linked === [] ? null : $this->block('Blog articles the text links to (festilaw:post/ID)', $linked),
            $claims === [] ? null : $this->block('Statements of the article and their basis, as the writer listed them (RULES: the reference of EU product rules)', $claims),
            $this->block('Article to check', $written->forPrompt()),
        ]));
        $reply = $this->ask($context, $agent, $prompt);
        $checked = EditorialArticle::fromArray($reply->structured());
        $problems = $this->problems($written, $checked);
        $usage = $reply->usage;

        if ($problems !== []) {
            $reply = $this->ask($context, $agent, spent: $usage, prompt: $prompt."\n\n".$this->block('Your previous answer', $reply->structured())
                ."\n\n".$this->block('Your answer added material the article to check does not contain. Remove it and answer again in full', '- '.implode("\n- ", $problems)));
            $usage = $usage->plus($reply->usage);
            $checked = EditorialArticle::fromArray($reply->structured());
            $problems = $this->problems($written, $checked);
        }

        if ($problems !== []) {
            $this->unusable('the checked article adds material or loses an image: '.implode('; ', $problems), $usage);
        }

        $removed = array_values(array_filter(array_map(
            fn ($claim): array => is_array($claim) ? ['claim' => $this->text($claim, 'claim'), 'reason' => $this->text($claim, 'reason')] : [],
            $this->items($reply->structured(), 'removed_claims'),
        ), static fn (array $claim): bool => ($claim['claim'] ?? '') !== ''));

        $conflicts = $this->referenceConflicts($reply->structured(), $context->generationId);

        return GenerationStepResult::continue(
            ['article' => $checked->toArray(), 'removed_claims' => $removed, 'attention_points' => $this->attentionPoints($reply->structured()), 'reference_conflicts' => $conflicts],
            $usage,
            $this->report($reply, $removed === []
                ? __('Chaque affirmation de l\'article est appuyée par le dossier de recherche.')
                : trans_choice('{1} Une affirmation non appuyée par le dossier a été retirée.|[2,*] :count affirmations non appuyées par le dossier ont été retirées.', count($removed), ['count' => count($removed)])),
        );
    }

    /**
     * Les affirmations qui suivent la reference des regles europeennes alors qu'une source officielle des
     * preuves la contredit (controle du 20/11) : la reference est peut-etre depassee. La verification ne
     * tranche pas, elle en fait un point d'attention pour la relectrice ; chaque conflit est journalise en
     * erreur, donc porte par le rapport d'erreurs du matin, pour faire relire la reference.
     *
     * @param  array<string, mixed>  $answer
     * @return list<array{statement: string, source: string}>
     */
    private function referenceConflicts(array $answer, int $generationId): array
    {
        $conflicts = array_values(array_filter(array_map(fn ($conflict): array => is_array($conflict) ? [
            'statement' => $this->text($conflict, 'statement'),
            'source' => $this->text($conflict, 'source'),
        ] : [], $this->items($answer, 'reference_conflicts')), static fn (array $conflict): bool => ($conflict['statement'] ?? '') !== ''));

        foreach ($conflicts as $conflict) {
            Log::error('EU rules reference contradicted by an official source: '.Str::limit($conflict['statement'], 160), ['generation_id' => $generationId, 'source' => $conflict['source']]);
        }

        return $conflicts;
    }

    /**
     * Les points d'attention complets, trois au plus. La consigne en demande peu, et la plupart des articles
     * n'en ont aucun : une longue liste ferait croire que l'outil doute de tout (Micha, 19/11).
     *
     * @param  array<string, mixed>  $answer
     * @return list<array{heading: string, statement_fr: string, check_fr: string}>
     */
    private function attentionPoints(array $answer): array
    {
        $points = array_map(fn ($point): array => is_array($point) ? [
            'heading' => $this->text($point, 'heading'),
            'statement_fr' => $this->text($point, 'statement_fr'),
            'check_fr' => $this->text($point, 'check_fr'),
        ] : [], $this->items($answer, 'attention_points'));

        return array_slice(array_values(array_filter(
            $points,
            static fn (array $point): bool => ($point['statement_fr'] ?? '') !== '' && ($point['check_fr'] ?? '') !== '',
        )), 0, self::MAX_ATTENTION_POINTS);
    }

    /**
     * Ce que la version verifiee ajoute au texte de depart (chiffre, lien, jeton) ou en perd (emplacement
     * d'image), dit au modele pour qu'il corrige. Vide : la version est acceptable.
     *
     * @return list<string>
     */
    private function problems(EditorialArticle $written, EditorialArticle $checked): array
    {
        if ($checked->isEmpty()) {
            return ['the article came back empty'];
        }

        $problems = array_values(array_filter(
            $this->guard->differences($written->text(), $checked->text()),
            static fn (string $difference): bool => ! str_ends_with($difference, 'added []'),
        ));
        $lostImages = array_diff(EditorialMarkdown::imagePlaceholders($written->body), EditorialMarkdown::imagePlaceholders($checked->body));

        if ($lostImages !== []) {
            $problems[] = 'image placeholders lost: '.implode(', ', $lostImages);
        }

        return $problems;
    }
}
