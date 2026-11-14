<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Previent par e-mail qu'un lancement est termine : une generation lancee au back-office, ou les N
 * generations d'un lot planifie. Appelee a la fin de chaque generation ; elle ne fait rien tant qu'une
 * generation du lot reste a traiter, et n'envoie qu'un message par lot (marquage atomique, une seule
 * ecriture). Un envoi qui echoue est journalise, jamais leve.
 */
final readonly class NotifyFinishedBlogGenerationBatchAction
{
    public function __construct(
        private BlogAiSettingsService $settings,
        private BlogAiCostFormatter $costs,
        private UrlGenerator $url,
    ) {}

    public function execute(string $batchUuid): void
    {
        $generations = BlogGeneration::query()
            ->where('batch_uuid', $batchUuid)
            ->orderBy('id')
            ->get(['id', 'trigger', 'status', 'outcome_reason', 'topic_title', 'topic_hint', 'cost_microusd']);

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

        $rows = $generations->map(fn (BlogGeneration $generation): array => [
            'subject' => $generation->topic_title ?: ($generation->topic_hint ?: __('Sujet choisi par l\'IA')),
            'outcome' => $generation->outcome_reason?->label() ?? $generation->status->label(),
            'succeeded' => $generation->status === BlogGenerationStatus::Completed,
            'cost' => $this->costs->dollars($generation->cost_microusd),
        ])->values()->all();

        try {
            Mail::to($this->settings->current()->notificationEmail)->send(new BlogGenerationBatchFinished(
                rows: $rows,
                scheduled: $generations->first()->trigger === BlogGenerationTrigger::Scheduled,
                totalCost: $this->costs->dollars((int) $generations->sum('cost_microusd')),
                url: $this->url->route('admin.blog.posts.index'),
            ));
        } catch (Throwable $e) {
            Log::error('Blog generation batch message failed to send.', ['batch_uuid' => $batchUuid, 'exception' => $e]);
        }
    }
}
