<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\MonthlySpendLevel;
use App\Mail\BlogGenerationFailuresAlert;
use App\Mail\BlogMonthlySpendWarning;
use App\Mail\BlogSpendLimitReached;
use App\Models\BlogGeneration;
use App\Repositories\Blog\BlogGenerationSeriesRepository;
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
 * Alertes de la generation par IA : une serie de generations d'articles sans brouillon (au prestataire
 * technique), et au destinataire des notifications les raisons d'argent. Ce sont le credit epuise chez le
 * fournisseur, l'approche puis le depassement du plafond du mois (une fois par mois et par cause). Envoi
 * au mieux : un e-mail qui ne part pas est journalise, jamais leve, pour ne pas faire echouer la generation
 * elle-meme, et son repere ne s'ecrit pas, si bien qu'il est retente a la fin de la generation suivante.
 * Aucun de ces e-mails ne porte la cause technique d'un echec : elle n'est ecrite qu'au journal du serveur.
 */
final readonly class BlogGenerationAlertService
{
    private const SPEND_ALERT_KEY = 'blog.ai.spend_alert.';

    /** La derniere generation reussie avant la serie deja signalee (controle du 20/11). */
    public const SERIES_ALERT_KEY = 'blog.ai.failure_alert.after_generation';

    private const MAX_SERIES_ROWS = 10;

    public function __construct(
        private SettingRepository $settings,
        private BlogGenerationBudget $budget,
        private BlogAiCostFormatter $costs,
        private BlogGenerationLabels $labels,
        private FailedBlogGenerationStepRepository $failedSteps,
        private BlogGenerationSeriesRepository $series,
        private UrlGenerator $url,
        private int $failuresThreshold,
        private ?string $techEmail,
        private string $fallbackEmail,
    ) {}

    /**
     * A appeler quand une generation finit sans brouillon (echec ou abandon). Alerte quand les generations
     * d'articles terminees depuis la derniere reussie sont toutes sans brouillon, des que leur nombre atteint
     * le seuil, quelle qu'en soit la raison : un blog qui ne produit plus, faute de sujet ou par un arret de
     * securite a chaque fois, doit se voir autant qu'une panne. Une alerte par serie.
     */
    public function afterArticleWithoutDraft(): void
    {
        $anchor = $this->series->lastArticleWithDraftId();
        $count = $this->series->countWithoutDraftAfter($anchor);

        if ($count < $this->failuresThreshold || $this->settings->get(self::SERIES_ALERT_KEY) === (string) $anchor) {
            return;
        }

        $streak = $this->series->latestWithoutDraftAfter($anchor, self::MAX_SERIES_ROWS);
        $sent = $this->send(
            $this->techEmail ?: $this->fallbackEmail,
            new BlogGenerationFailuresAlert($count, $this->seriesRows($streak), $this->url->route('admin.blog.generation.index')),
            'Blog generation failures alert failed to send.',
        );

        if ($sent) {
            $this->settings->put(self::SERIES_ALERT_KEY, (string) $anchor);
        }
    }

    /**
     * Une ligne par generation de la serie : son sujet, ce qui l'a arretee, sa page au back-office.
     *
     * @param  Collection<int, BlogGeneration>  $streak
     * @return list<array{subject: string, outcome: string, url: string}>
     */
    private function seriesRows(Collection $streak): array
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
        if ($this->sentThisMonth('credit')) {
            return;
        }

        $this->sendOncePerMonth('credit', $settings->notificationEmail, new BlogSpendLimitReached(
            $this->costs->monthDollars($this->budget->spentThisMonthMicrousd()),
            $this->costs->monthDollars($settings->monthlySpendCapMicrousd()),
        ), 'Blog spend limit message failed to send.');
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

        if ($level === MonthlySpendLevel::Under || $this->sentThisMonth($level->value)) {
            return;
        }

        $this->sendOncePerMonth($level->value, $settings->notificationEmail, new BlogMonthlySpendWarning(
            $level === MonthlySpendLevel::Over,
            $this->costs->monthDollars($spent),
            $this->costs->monthDollars($cap),
            intdiv(100 * $spent, max(1, $cap)),
            $this->url->route('admin.blog.generation.settings'),
        ), 'Blog monthly spend warning failed to send.');
    }

    private function sentThisMonth(string $cause): bool
    {
        return $this->settings->get(self::SPEND_ALERT_KEY.$cause) === CarbonImmutable::now()->format('Y-m');
    }

    /** Le mois n'est note qu'apres un envoi reussi : un envoi rate est retente a la fin de la generation suivante. */
    private function sendOncePerMonth(string $cause, string $recipient, Mailable $mail, string $failureMessage): void
    {
        if ($this->send($recipient, $mail, $failureMessage)) {
            $this->settings->put(self::SPEND_ALERT_KEY.$cause, CarbonImmutable::now()->format('Y-m'));
        }
    }

    private function send(string $recipient, Mailable $mail, string $failureMessage): bool
    {
        try {
            Mail::to($recipient)->send($mail);

            return true;
        } catch (Throwable $e) {
            Log::error($failureMessage, ['exception' => $e]);

            return false;
        }
    }
}
