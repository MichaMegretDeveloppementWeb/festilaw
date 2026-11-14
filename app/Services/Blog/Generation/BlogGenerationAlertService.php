<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Mail\BlogGenerationFailuresAlert;
use App\Mail\BlogSpendLimitReached;
use App\Models\BlogGeneration;
use App\Repositories\Blog\FailedBlogGenerationStepRepository;
use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alertes de la generation par IA : une serie d'echecs (au prestataire technique), et la suspension pour une
 * raison d'argent (au destinataire des notifications, une fois par mois et par cause). Envoi au mieux : un
 * e-mail qui ne part pas est journalise, jamais leve, pour ne pas faire echouer la generation elle-meme.
 * Aucun de ces e-mails ne porte la cause technique d'un echec : elle n'est ecrite qu'au journal du serveur.
 */
final readonly class BlogGenerationAlertService
{
    private const SPEND_ALERT_KEY = 'blog.ai.spend_alert.';

    public function __construct(
        private SettingRepository $settings,
        private BlogGenerationBudget $budget,
        private BlogAiCostFormatter $costs,
        private BlogGenerationLabels $labels,
        private FailedBlogGenerationStepRepository $failedSteps,
        private UrlGenerator $url,
        private int $failuresThreshold,
        private ?string $techEmail,
        private string $fallbackEmail,
    ) {}

    /**
     * A appeler apres l'echec d'une generation. Alerte quand les dernieres generations executees sont toutes
     * en echec et que la serie vient d'atteindre le seuil : une seule alerte par serie.
     */
    public function afterFailure(): void
    {
        $statuses = BlogGeneration::query()
            ->whereNotNull('started_at')
            ->whereNotIn('status', BlogGenerationStatus::activeValues())
            ->latest('id')
            ->limit($this->failuresThreshold + 1)
            ->get(['id', 'status', 'outcome_reason', 'topic_title', 'topic_hint']);

        $streak = $statuses->takeWhile(static fn (BlogGeneration $generation): bool => $generation->status === BlogGenerationStatus::Failed);

        if ($streak->count() !== $this->failuresThreshold) {
            return;
        }

        $this->send(
            $this->techEmail ?: $this->fallbackEmail,
            new BlogGenerationFailuresAlert($this->failuresThreshold, $this->failureRows($streak), $this->url->route('admin.blog.generation.index')),
            'Blog generation failures alert failed to send.',
        );
    }

    /**
     * Une ligne par generation de la serie : son sujet, l'etape ou elle a echoue, sa page au back-office.
     *
     * @param  Collection<int, BlogGeneration>  $streak
     * @return list<array{subject: string, outcome: string, url: string}>
     */
    private function failureRows(Collection $streak): array
    {
        $failedSteps = $this->failedSteps->stepByGeneration($streak->map(static fn (BlogGeneration $generation): int => $generation->id)->values()->all());

        return $streak->map(fn (BlogGeneration $generation): array => [
            'subject' => $this->labels->subject($generation->topic_title, $generation->topic_hint),
            'outcome' => $this->labels->outcome($generation->outcome_reason, $failedSteps[$generation->id] ?? null) ?? $generation->status->label(),
            'url' => $this->url->route('admin.blog.generation.show', ['generation' => $generation->id]),
        ])->values()->all();
    }

    /** Suspension pour une raison d'argent : plafond du back-office atteint, ou credit epuise chez le fournisseur. */
    public function spendLimitReached(BlogAiSettings $settings, bool $creditExhausted): void
    {
        $key = self::SPEND_ALERT_KEY.($creditExhausted ? 'credit' : 'cap');
        $month = CarbonImmutable::now()->format('Y-m');

        if ($this->settings->get($key) === $month) {
            return;
        }

        $this->settings->put($key, $month);

        $this->send(
            $settings->notificationEmail,
            new BlogSpendLimitReached(
                $creditExhausted,
                $this->costs->dollars($this->budget->spentThisMonthMicrousd()),
                $this->costs->dollars($settings->monthlySpendCapMicrousd()),
            ),
            'Blog spend limit message failed to send.',
        );
    }

    private function send(string $recipient, BlogGenerationFailuresAlert|BlogSpendLimitReached $mail, string $failureMessage): void
    {
        try {
            Mail::to($recipient)->send($mail);
        } catch (Throwable $e) {
            Log::error($failureMessage, ['exception' => $e]);
        }
    }
}
