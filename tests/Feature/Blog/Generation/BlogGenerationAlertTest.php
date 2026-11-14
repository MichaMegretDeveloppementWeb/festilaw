<?php

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Mail\BlogGenerationFailuresAlert;
use App\Mail\BlogSpendLimitReached;
use App\Models\BlogGeneration;
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

it('falls back on the notification address when no technical contact is set', function () {
    config(['festilaw.tech_alert_email' => null, 'festilaw.notification_email' => 'team@example.com']);

    processGenerationThat(fails: true);
    processGenerationThat(fails: true);
    processGenerationThat(fails: true);

    Mail::assertSent(BlogGenerationFailuresAlert::class, fn (BlogGenerationFailuresAlert $mail): bool => $mail->hasTo('team@example.com'));
});

it('tells about a spend limit once a month for each cause', function () {
    $settings = BlogAi::saveSettings(['notification_email' => 'owner@example.com']);
    $alerts = app(BlogGenerationAlertService::class);
    $this->travelTo('2026-11-14 10:00:00');

    $alerts->spendLimitReached($settings, creditExhausted: false);
    $alerts->spendLimitReached($settings, creditExhausted: false);
    $alerts->spendLimitReached($settings, creditExhausted: true);
    Mail::assertSent(BlogSpendLimitReached::class, 2);

    $this->travelTo('2026-12-01 10:00:00');
    $alerts->spendLimitReached($settings, creditExhausted: false);
    Mail::assertSent(BlogSpendLimitReached::class, 3);
});

it('renders both alert messages', function () {
    $failures = (new BlogGenerationFailuresAlert(
        3,
        [['subject' => 'Labels on Etsy', 'outcome' => 'Échec à l\'étape « Recherche »', 'url' => 'https://example.test/admin/blog/generation/4']],
        'https://example.test/admin/blog/generation',
    ))->render();
    $cap = (new BlogSpendLimitReached(false, '10,00 $', '10,00 $'))->render();
    $credit = (new BlogSpendLimitReached(true, '4,20 $', '10,00 $'))->render();

    expect($failures)->toContain('3 dernières générations')
        ->toContain('Labels on Etsy')
        ->toContain('étape « Recherche »')
        ->toContain('href="https://example.test/admin/blog/generation/4"')
        ->toContain('storage/logs/laravel.log')
        ->and($cap)->toContain('Plafond de dépense atteint')->toContain('10,00 $')
        ->and($credit)->toContain('Crédit d\'IA épuisé')->toContain('4,20 $');
});
