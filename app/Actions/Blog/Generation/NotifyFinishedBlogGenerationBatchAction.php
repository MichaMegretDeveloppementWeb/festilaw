<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Repositories\Blog\BlogGeneratedDraftRepository;
use App\Repositories\Blog\FailedBlogGenerationStepRepository;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationLabels;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Previent par e-mail qu'un lancement est termine : une generation lancee au back-office, ou les N
 * generations d'un lot planifie. Appelee a la fin de chaque generation ; elle ne fait rien tant qu'une
 * generation du lot reste a traiter, et n'envoie qu'un message par lot (marquage atomique, une seule
 * ecriture). Le message dit, pour chaque generation, si elle a reussi ou a quelle etape elle a echoue, et
 * mene a sa page ; jamais la cause technique, qui ne va qu'au journal du serveur.
 *
 * Un lancement dont toutes les generations ont ete arretees au back-office ne previent personne : la
 * personne qui les a arretees le sait, et son clic n'a pas a attendre un envoi. Un envoi qui echoue est
 * journalise, jamais leve.
 */
final readonly class NotifyFinishedBlogGenerationBatchAction
{
    public function __construct(
        private BlogAiSettingsService $settings,
        private BlogAiCostFormatter $costs,
        private BlogGenerationLabels $labels,
        private FailedBlogGenerationStepRepository $failedSteps,
        private BlogGeneratedDraftRepository $drafts,
        private UrlGenerator $url,
    ) {}

    public function execute(string $batchUuid): void
    {
        $generations = BlogGeneration::query()
            ->where('batch_uuid', $batchUuid)
            ->orderBy('id')
            ->get(['id', 'type', 'trigger', 'status', 'outcome_reason', 'topic_title', 'topic_hint', 'blog_post_id', 'cost_microusd']);

        if ($generations->isEmpty() || $generations->contains(static fn (BlogGeneration $generation): bool => ! $generation->status->isTerminal())) {
            return;
        }

        $marked = BlogGeneration::query()
            ->where('batch_uuid', $batchUuid)
            ->whereNull('batch_notified_at')
            ->update(['batch_notified_at' => CarbonImmutable::now()]);

        if ($marked === 0) {
            return;
        }

        // Une traduction assistee ou une image demandee depuis un article se suit dans le formulaire qui l'a demandee :
        // pas d'e-mail.
        if ($generations->every(static fn (BlogGeneration $generation): bool => $generation->outcome_reason === BlogGenerationOutcomeReason::CancelledByUser || in_array($generation->type, [BlogGenerationType::Translation, BlogGenerationType::Image], true))) {
            return;
        }

        $failedSteps = $this->failedSteps->stepByGeneration(
            $generations
                ->filter(static fn (BlogGeneration $generation): bool => $generation->status === BlogGenerationStatus::Failed)
                ->map(static fn (BlogGeneration $generation): int => $generation->id)
                ->values()
                ->all(),
        );

        $drafts = $this->drafts->summariesOf($generations->pluck('blog_post_id')->filter()->map(static fn (mixed $id): int => (int) $id)->values()->all());

        $rows = $generations->map(fn (BlogGeneration $generation): array => [
            'subject' => $drafts[$generation->blog_post_id ?? 0]['title'] ?? $this->labels->subject($generation->topic_title, $generation->topic_hint),
            'outcome' => $this->labels->outcome($generation->outcome_reason, $failedSteps[$generation->id] ?? null) ?? $generation->status->label(),
            'succeeded' => $generation->status === BlogGenerationStatus::Completed,
            'failed' => $generation->status === BlogGenerationStatus::Failed,
            'cost' => $this->costs->dollars($generation->cost_microusd),
            'url' => $this->url->route('admin.blog.generation.show', ['generation' => $generation->id]),
            'draftUrl' => $generation->blog_post_id === null ? null : $this->url->route('admin.blog.posts.edit', ['post' => $generation->blog_post_id]),
            'excerpt' => $drafts[$generation->blog_post_id ?? 0]['excerpt'] ?? null,
            'sources' => $drafts[$generation->blog_post_id ?? 0]['sources'] ?? null,
            'newCategory' => $drafts[$generation->blog_post_id ?? 0]['newCategory'] ?? null,
        ])->values()->all();

        try {
            Mail::to($this->settings->current()->notificationEmail)->send(new BlogGenerationBatchFinished(
                rows: $rows,
                scheduled: $generations->first()->trigger === BlogGenerationTrigger::Scheduled,
                totalCost: $this->costs->dollars((int) $generations->sum('cost_microusd')),
                postsUrl: $this->url->route('admin.blog.posts.index'),
                generationsUrl: $this->url->route('admin.blog.generation.index'),
            ));
        } catch (Throwable $e) {
            Log::error('Blog generation batch message failed to send.', ['batch_uuid' => $batchUuid, 'exception' => $e]);
        }
    }
}
