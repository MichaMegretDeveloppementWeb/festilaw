<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Models\BlogGeneration;
use App\Models\BlogGenerationStep;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Generations lues par le back-office : une page de l'historique, les dernieres pour la vue d'ensemble, et
 * une generation avec le nom des personnes qui l'ont lancee et arretee. Les plus recentes d'abord. Pour une
 * generation terminee, les passages que la verification et le controle des versions ont retires ou corriges.
 * Chacune porte le titre francais de l'article qu'elle a cree (`post_title`), lu par une jointure de la meme
 * requete : le back-office l'affiche a la place du sujet de travail, en anglais (decision de Micha du 19/11).
 */
final readonly class AdminBlogGenerationJournalRepository
{
    private const LIST_COLUMNS = [
        'blog_generations.id', 'blog_generations.trigger', 'blog_generations.status', 'blog_generations.outcome_reason',
        'blog_generations.topic_title', 'blog_generations.topic_hint', 'blog_generations.blog_post_id',
        'blog_generations.cost_microusd', 'blog_generations.started_at', 'blog_generations.finished_at',
        'blog_generations.created_at', 'post_texts.title as post_title',
    ];

    /** Langue du titre de l'article montre au back-office. */
    private const POST_TITLE_LOCALE = 'fr';

    /** @return LengthAwarePaginator<int, BlogGeneration> */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->newest()->paginate($perPage);
    }

    /** @return Collection<int, BlogGeneration> */
    public function latest(int $limit): Collection
    {
        return $this->newest()->limit($limit)->get();
    }

    /**
     * Une generation pour son ecran, avec le nom de qui l'a lancee (`requester_name`) et de qui l'a arretee
     * (`canceller_name`), en une seule requete. Ni ce qu'elle a produit, ni la cause technique d'un echec.
     */
    public function findWithPeople(int $generationId): ?BlogGeneration
    {
        return $this->withPostTitle(BlogGeneration::query())
            ->leftJoin('users as requesters', 'requesters.id', '=', 'blog_generations.requested_by')
            ->leftJoin('users as cancellers', 'cancellers.id', '=', 'blog_generations.cancel_requested_by')
            ->where('blog_generations.id', $generationId)
            ->first([
                'blog_generations.id', 'blog_generations.type', 'blog_generations.trigger', 'blog_generations.status',
                'blog_generations.outcome_reason', 'blog_generations.topic_title', 'blog_generations.topic_hint',
                'blog_generations.instructions', 'blog_generations.blog_post_id', 'blog_generations.payload',
                'blog_generations.cost_microusd', 'blog_generations.forecast_low_microusd', 'blog_generations.forecast_high_microusd',
                'blog_generations.started_at', 'blog_generations.finished_at',
                'blog_generations.cancel_requested_at', 'blog_generations.created_at',
                'requesters.name as requester_name', 'cancellers.name as canceller_name', 'post_texts.title as post_title',
            ]);
    }

    /**
     * Les passages retires ou corriges par la verification et par le controle des versions, dans l'ordre des
     * etapes. Seuls ces deux champs de la sortie sont lus, jamais le texte de l'article.
     *
     * @return list<array{step: string, passage: string, reason: string}>
     */
    public function correctedPassages(int $generationId): array
    {
        $rows = BlogGenerationStep::query()->toBase()
            ->where('blog_generation_id', $generationId)
            ->where('status', BlogGenerationStepStatus::Completed->value)
            ->whereIn('step', ['verification', 'version_check_fr', 'version_check_es'])
            ->orderBy('position')
            ->get(['step', 'output->removed_claims as removed_claims', 'output->corrections as corrections']);
        $passages = [];

        foreach ($rows as $row) {
            $items = json_decode((string) ($row->removed_claims ?? $row->corrections ?? '[]'), true);

            foreach (is_array($items) ? $items : [] as $item) {
                $passage = is_array($item) ? trim((string) ($item['claim'] ?? $item['original'] ?? '')) : '';

                if ($passage !== '') {
                    $passages[] = ['step' => (string) $row->step, 'passage' => $passage, 'reason' => trim((string) ($item['reason'] ?? ''))];
                }
            }
        }

        return $passages;
    }

    /** @return Builder<BlogGeneration> */
    private function newest(): Builder
    {
        return $this->withPostTitle(BlogGeneration::query())->select(self::LIST_COLUMNS)->latest('blog_generations.id');
    }

    /**
     * @param  Builder<BlogGeneration>  $query
     * @return Builder<BlogGeneration>
     */
    private function withPostTitle(Builder $query): Builder
    {
        return $query->leftJoin('blog_post_translations as post_texts', static function ($join): void {
            $join->on('post_texts.blog_post_id', '=', 'blog_generations.blog_post_id')
                ->where('post_texts.locale', '=', self::POST_TITLE_LOCALE);
        });
    }
}
