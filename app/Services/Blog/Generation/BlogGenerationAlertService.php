<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\ArticleSpendLimit;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\MonthlySpendLevel;
use App\Mail\BlogForecastAboveCap;
use App\Mail\BlogGenerationFailuresAlert;
use App\Mail\BlogMonthlySpendWarning;
use App\Mail\BlogSpendLimitReached;
use App\Models\BlogGeneration;
use App\Repositories\Blog\FailedBlogGenerationStepRepository;
use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alertes de la generation par IA : une serie d'echecs (au prestataire technique), et au destinataire des
 * notifications les raisons d'argent. Ce sont le credit epuise chez le fournisseur, l'approche puis le
 * depassement du plafond du mois (une fois par mois et par cause), et le lot automatique retenu par le cout
 * estime. Envoi au mieux : un e-mail qui ne part pas est journalise, jamais leve, pour ne pas faire echouer la
 * generation elle-meme. Aucun de ces e-mails ne porte la cause technique d'un echec : elle n'est ecrite qu'au
 * journal du serveur.
 */
final readonly class BlogGenerationAlertService
{
    private const SPEND_ALERT_KEY = 'blog.ai.spend_alert.';

    public function __construct(
        private SettingRepository $settings,
        private BlogGenerationBudget $budget,
        private BlogAiCostFormatter $costs,
        private BlogGenerationLabels $labels,
        private BlogGenerationCostForecaster $forecaster,
        private ArticleSpendLimit $spendLimit,
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

    /** Le credit du compte chez le fournisseur d'IA est epuise : la generation s'arrete jusqu'au rechargement. */
    public function creditExhausted(BlogAiSettings $settings): void
    {
        if (! $this->firstThisMonth('credit')) {
            return;
        }

        $this->send(
            $settings->notificationEmail,
            new BlogSpendLimitReached(
                $this->costs->dollars($this->budget->spentThisMonthMicrousd()),
                $this->costs->dollars($settings->monthlySpendCapMicrousd()),
            ),
            'Blog spend limit message failed to send.',
        );
    }

    /**
     * A appeler a la fin de chaque generation : la depense ne bouge qu'a ce moment-la. Avertit quand elle
     * approche du plafond du mois, puis quand elle le depasse, une fois par mois pour chacun des deux seuils.
     * Une generation qui saute le premier seuil n'envoie que le second.
     */
    public function monthlySpend(BlogAiSettings $settings): void
    {
        $spent = $this->budget->spentThisMonthMicrousd();
        $cap = $settings->monthlySpendCapMicrousd();
        $level = $this->budget->monthlyLevel($spent, $cap);

        if ($level === MonthlySpendLevel::Under || ! $this->firstThisMonth($level->value)) {
            return;
        }

        $this->send(
            $settings->notificationEmail,
            new BlogMonthlySpendWarning(
                $level === MonthlySpendLevel::Over,
                $this->costs->dollars($spent),
                $this->costs->dollars($cap),
                intdiv(100 * $spent, max(1, $cap)),
                $this->url->route('admin.blog.generation.settings'),
            ),
            'Blog monthly spend warning failed to send.',
        );
    }

    /** Vrai la premiere fois du mois pour cette cause, alors notee pour ne pas renvoyer le meme message. */
    private function firstThisMonth(string $cause): bool
    {
        $key = self::SPEND_ALERT_KEY.$cause;
        $month = CarbonImmutable::now()->format('Y-m');

        if ($this->settings->get($key) === $month) {
            return false;
        }

        $this->settings->put($key, $month);

        return true;
    }

    /** La generation automatique retient son lot : un article peut couter plus que le plafond par article augmente de sa marge. */
    public function forecastAboveArticleCap(BlogAiSettings $settings): void
    {
        $forecast = $this->forecaster->forSettings($settings);
        $cap = $settings->articleSpendCapMicrousd();

        $this->send(
            $settings->notificationEmail,
            new BlogForecastAboveCap(
                $this->costs->dollars($forecast->lowMicrousd),
                $this->costs->dollars($forecast->highMicrousd),
                $this->costs->dollars($cap),
                $this->costs->dollars($this->spendLimit->limit($cap)),
                $this->spendLimit->percent,
                $this->url->route('admin.blog.generation.settings'),
            ),
            'Blog forecast above cap message failed to send.',
        );
    }

    private function send(string $recipient, Mailable $mail, string $failureMessage): void
    {
        try {
            Mail::to($recipient)->send($mail);
        } catch (Throwable $e) {
            Log::error($failureMessage, ['exception' => $e]);
        }
    }
}
