<?php

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Mail\BlogGenerationFailuresAlert;
use App\Mail\BlogMonthlySpendWarning;
use App\Mail\BlogSpendLimitReached;
use App\Models\BlogGeneration;
use App\Repositories\SettingRepository;
use App\Services\Blog\Generation\BlogGenerationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\Support\BlogAi;
use Tests\Support\FakeGenerationPipeline;

/*
 | Alertes de la generation : une serie d'echecs previent le prestataire technique, une seule fois par serie ;
 | une suspension pour une raison d'argent previent le destinataire des notifications, une fois par mois.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    config(['festilaw.tech_alert_email' => 'tech@example.com']);
});

/** Traite la generation suivante avec une etape qui echoue toujours, ou qui reussit. */
function processGenerationThat(bool $fails): void
{
    $pipeline = FakeGenerationPipeline::bind(['topic']);

    if ($fails) {
        $pipeline->on('topic', fn () => throw new RuntimeException('Provider is down.'));
    }

    BlogGeneration::factory()->create();
    app(ProcessNextBlogGenerationAction::class)->execute('worker-a');
}

it('warns the technical contact when three generations fail in a row, once for the series', function () {
    processGenerationThat(fails: true);
    processGenerationThat(fails: true);
    Mail::assertNotSent(BlogGenerationFailuresAlert::class);

    processGenerationThat(fails: true);
    Mail::assertSent(BlogGenerationFailuresAlert::class, 1);
    Mail::assertSent(BlogGenerationFailuresAlert::class, fn (BlogGenerationFailuresAlert $mail): bool => $mail->hasTo('tech@example.com')
        && $mail->failures === 3
        && count($mail->rows) === 3
        && $mail->rows[0]['outcome'] === 'Échec à l\'étape « Choix du sujet »'
        && $mail->rows[0]['url'] === route('admin.blog.generation.show', ['generation' => BlogGeneration::query()->max('id')])
        && $mail->generationsUrl === route('admin.blog.generation.index'));

    // La serie continue : pas de nouvelle alerte.
    processGenerationThat(fails: true);
    Mail::assertSent(BlogGenerationFailuresAlert::class, 1);
});

it('starts a new series after a success', function () {
    processGenerationThat(fails: true);
    processGenerationThat(fails: true);
    processGenerationThat(fails: false);
    processGenerationThat(fails: true);
    processGenerationThat(fails: true);
    Mail::assertNotSent(BlogGenerationFailuresAlert::class);

    processGenerationThat(fails: true);
    Mail::assertSent(BlogGenerationFailuresAlert::class, 1);
});

it('counts every article generation that ended without a draft, whatever stopped it', function () {
    BlogGeneration::factory()->completed()->create();
    BlogGeneration::factory()->failed()->create();
    BlogGeneration::factory()->abandoned(BlogGenerationOutcomeReason::SafetyStop)->create(['started_at' => now()]);
    BlogGeneration::factory()->cancelled()->create(['started_at' => now()]);
    BlogGeneration::factory()->failed()->create(['type' => BlogGenerationType::Translation]);
    BlogGeneration::factory()->abandoned(BlogGenerationOutcomeReason::PendingDraftsCapReached)->scheduled()->create();
    $alerts = app(BlogGenerationAlertService::class);

    $alerts->afterArticleWithoutDraft();
    Mail::assertNotSent(BlogGenerationFailuresAlert::class);

    BlogGeneration::factory()->abandoned(BlogGenerationOutcomeReason::NoRelevantTopic)->create(['started_at' => now()]);
    $alerts->afterArticleWithoutDraft();

    Mail::assertSent(BlogGenerationFailuresAlert::class, fn (BlogGenerationFailuresAlert $mail): bool => $mail->failures === 3
        && array_column($mail->rows, 'outcome') === [
            'Aucun sujet assez pertinent',
            BlogGenerationOutcomeReason::SafetyStop->label(),
            'La génération a échoué',
        ]);
});

it('tries the series alert again at the next generation when it could not be sent', function () {
    BlogGeneration::factory()->count(3)->failed()->create();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail transport down.'));

    app(BlogGenerationAlertService::class)->afterArticleWithoutDraft();

    expect(app(SettingRepository::class)->get(BlogGenerationAlertService::SERIES_ALERT_KEY))->toBeNull();
});

it('falls back on the notification address when no technical contact is set', function () {
    config(['festilaw.tech_alert_email' => null, 'festilaw.notification_email' => 'team@example.com']);

    processGenerationThat(fails: true);
    processGenerationThat(fails: true);
    processGenerationThat(fails: true);

    Mail::assertSent(BlogGenerationFailuresAlert::class, fn (BlogGenerationFailuresAlert $mail): bool => $mail->hasTo('team@example.com'));
});

it('tells about the exhausted provider credit once a month', function () {
    $settings = BlogAi::saveSettings(['notification_email' => 'owner@example.com']);
    $alerts = app(BlogGenerationAlertService::class);
    $this->travelTo('2026-11-14 10:00:00');

    $alerts->creditExhausted($settings);
    $alerts->creditExhausted($settings);
    Mail::assertSent(BlogSpendLimitReached::class, 1);

    $this->travelTo('2026-12-01 10:00:00');
    $alerts->creditExhausted($settings);
    Mail::assertSent(BlogSpendLimitReached::class, 2);
});

it('warns once a month as the spend nears the monthly cap, then once more when it passes it', function () {
    $settings = BlogAi::saveSettings(['monthly_spend_cap_cents' => 100, 'notification_email' => 'owner@example.com']);
    $alerts = app(BlogGenerationAlertService::class);
    $this->travelTo('2026-11-14 10:00:00');
    $spent = BlogGeneration::factory()->completed(costMicrousd: 700_000)->create();

    $alerts->monthlySpend($settings);
    Mail::assertNothingSent();

    $spent->update(['cost_microusd' => 850_000]);
    $alerts->monthlySpend($settings);
    $alerts->monthlySpend($settings);
    Mail::assertSent(BlogMonthlySpendWarning::class, 1);
    Mail::assertSent(BlogMonthlySpendWarning::class, fn (BlogMonthlySpendWarning $mail): bool => ! $mail->over
        && $mail->percent === 85
        && $mail->spent === dollarsText('0,85 $')
        && $mail->hasTo('owner@example.com'));

    $spent->update(['cost_microusd' => 1_200_000]);
    $alerts->monthlySpend($settings);
    $alerts->monthlySpend($settings);
    Mail::assertSent(BlogMonthlySpendWarning::class, 2);
    Mail::assertSent(BlogMonthlySpendWarning::class, fn (BlogMonthlySpendWarning $mail): bool => $mail->over && $mail->cap === dollarsText('1,00 $'));

    // Le mois suivant, la depense repart de zero, et chaque seuil peut de nouveau avertir une fois.
    $this->travelTo('2026-12-01 10:00:00');
    $alerts->monthlySpend($settings);
    BlogGeneration::factory()->completed(costMicrousd: 900_000)->create();
    $alerts->monthlySpend($settings);
    Mail::assertSent(BlogMonthlySpendWarning::class, 3);
});

it('renders the alert messages', function () {
    $failures = (new BlogGenerationFailuresAlert(
        3,
        [['subject' => 'Labels on Etsy', 'outcome' => 'Échec à l\'étape « Recherche »', 'url' => 'https://example.test/admin/blog/generation/4']],
        'https://example.test/admin/blog/generation',
    ))->render();
    $credit = (new BlogSpendLimitReached('4,20 $', '10,00 $'))->render();
    $near = (new BlogMonthlySpendWarning(false, '8,50 $', '10,00 $', 85, 'https://example.test/admin/blog/generation/settings'))->render();
    $over = (new BlogMonthlySpendWarning(true, '12,00 $', '10,00 $', 120, 'https://example.test/admin/blog/generation/settings'))->render();

    expect($failures)->toContain('3 dernières générations')
        ->toContain('Labels on Etsy')
        ->toContain('étape « Recherche »')
        ->toContain('href="https://example.test/admin/blog/generation/4"')
        ->toContain('storage/logs, fichier laravel du jour')
        ->and($credit)->toContain('Crédit d\'IA épuisé')->toContain('4,20 $')
        ->and($near)->toContain('La dépense du mois approche du plafond')->toContain('85 %')->toContain('Dépasser le plafond ne bloque pas la génération.')->not->toContain('par lot')
        ->and($over)->toContain('Plafond de dépense du mois dépassé')->toContain('12,00 $')
        ->toContain('href="https://example.test/admin/blog/generation/settings"');
});
