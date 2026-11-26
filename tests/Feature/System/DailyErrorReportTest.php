<?php

declare(strict_types=1);

use App\Actions\System\SendDailyErrorReportAction;
use App\Mail\DailyErrorReport;
use App\Repositories\SettingRepository;
use App\Services\System\ErrorLogReader;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
 | Rapport d'erreurs du matin (demande de Micha du 20/11) : les erreurs du journal du serveur depuis le
 | dernier rapport partent au prestataire technique, rien ne part quand il n'y en a pas, et la periode
 | couverte n'avance qu'apres un envoi reussi.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->logs = storage_path('framework/testing/error-report-'.uniqid());
    File::ensureDirectoryExists($this->logs);
    app()->when(ErrorLogReader::class)->needs('$directory')->give(fn (): string => $this->logs);
    config()->set('festilaw.tech_alert_email', 'tech@example.com');
    config()->set('festilaw.notification_email', 'team@example.com');
    $this->travelTo('2026-11-21 06:00:00');
});

afterEach(function () {
    File::deleteDirectory($this->logs);
});

/** Ecrit un fichier du journal, date de l'heure du test (le rapport ne lit que les fichiers touches pendant sa periode). */
function writeLogFile(string $directory, string $name, string $content): void
{
    $path = $directory.DIRECTORY_SEPARATOR.$name;
    file_put_contents($path, $content);
    touch($path, now()->getTimestamp());
}

function basePathInLogs(): string
{
    return str_replace('\\', '/', base_path());
}

it('sends the errors since the last report to the technical contact, grouped and most frequent first', function () {
    writeLogFile($this->logs, 'laravel-2026-11-20.log', implode("\n", [
        '[2026-11-19 05:00:00] testing.ERROR: Too old to report. ',
        '[2026-11-20 08:15:00] testing.ERROR: Payment 12 could not be confirmed. {"payment":12} ',
        '[2026-11-20 09:00:00] testing.ERROR: Undefined variable $plan {"route":"pricing","method":"GET","exception":"[object] (ErrorException(code: 0): Undefined variable $plan at '.basePathInLogs().'/resources/views/web/pricing/index.blade.php:12)',
        '[stacktrace]',
        '#0 '.basePathInLogs().'/vendor/laravel/framework/src/Illuminate/View/Engines/PhpEngine.php(58): {closure}()',
        '"} ',
        '[2026-11-20 09:30:00] testing.ERROR: Payment 345 could not be confirmed. {"payment":345} ',
        '[2026-11-20 10:00:00] testing.WARNING: A step was retried. ',
        '[2026-11-20 11:00:00] local.ERROR: Written by a local run, not this environment. ',
        '',
    ]));
    writeLogFile($this->logs, 'payments-2026-11-21.log', "[2026-11-21 01:00:00] testing.CRITICAL: Stripe webhook could not be read. \n");
    Mail::fake();

    $this->artisan('festilaw:error-report')->assertOk();

    Mail::assertSent(DailyErrorReport::class, function (DailyErrorReport $mail): bool {
        $groups = $mail->summary->groups;

        return $mail->hasTo('tech@example.com')
            && $mail->summary->occurrences() === 4
            && count($groups) === 3
            && $groups[0]->message === 'Payment 12 could not be confirmed.' && $groups[0]->count === 2
            && $groups[1]->level === 'CRITICAL' && $groups[1]->file === 'payments.log'
            && $groups[2]->exceptionClass === 'ErrorException'
            && $groups[2]->location === 'resources/views/web/pricing/index.blade.php:12'
            && $groups[2]->routes === ['pricing'];
    });
    expect(app(SettingRepository::class)->get(SendDailyErrorReportAction::COVERED_UNTIL_KEY))->toBe('2026-11-21T06:00:00+00:00');
});

it('starts the next report where the previous one ended', function () {
    app(SettingRepository::class)->put(SendDailyErrorReportAction::COVERED_UNTIL_KEY, '2026-11-20T09:00:00+00:00');
    writeLogFile($this->logs, 'laravel-2026-11-20.log', implode("\n", [
        '[2026-11-20 08:00:00] testing.ERROR: Already reported. ',
        '[2026-11-20 10:00:00] testing.ERROR: New since the last report. ',
        '',
    ]));
    Mail::fake();

    $this->artisan('festilaw:error-report')->assertOk();

    Mail::assertSent(DailyErrorReport::class, fn (DailyErrorReport $mail): bool => array_map(fn ($group) => $group->message, $mail->summary->groups) === ['New since the last report.']);
});

it('sends nothing when the log has no error, and moves the covered period on', function () {
    writeLogFile($this->logs, 'laravel-2026-11-20.log', "[2026-11-20 10:00:00] testing.WARNING: Only a warning. \n");
    Mail::fake();

    $this->artisan('festilaw:error-report')->assertOk();

    Mail::assertNothingSent();
    expect(app(SettingRepository::class)->get(SendDailyErrorReportAction::COVERED_UNTIL_KEY))->toBe('2026-11-21T06:00:00+00:00');
});

it('keeps the start of a report that failed to send, so the next one covers its period too', function () {
    writeLogFile($this->logs, 'laravel-2026-11-20.log', "[2026-11-20 10:00:00] testing.ERROR: Lost if the period moved on. \n");
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail transport down.'));

    $this->artisan('festilaw:error-report')->assertFailed();

    expect(app(SettingRepository::class)->get(SendDailyErrorReportAction::COVERED_UNTIL_KEY))->toBe('2026-11-20T06:00:00+00:00');
});

it('sends nothing without a technical address, never falling back to the client', function () {
    config()->set('festilaw.tech_alert_email', null);
    writeLogFile($this->logs, 'laravel-2026-11-20.log', "[2026-11-20 10:00:00] testing.ERROR: Nobody to tell. \n");
    Mail::fake();

    $this->artisan('festilaw:error-report')->assertFailed();

    Mail::assertNothingSent();
});

it('shows the report without sending it or moving the covered period on, in a dry run', function () {
    writeLogFile($this->logs, 'laravel-2026-11-20.log', "[2026-11-20 10:00:00] testing.ERROR: Seen in the dry run. \n");
    Mail::fake();

    $this->artisan('festilaw:error-report', ['--dry' => true])
        ->expectsOutputToContain('Seen in the dry run.')
        ->assertOk();

    Mail::assertNothingSent();
    expect(app(SettingRepository::class)->get(SendDailyErrorReportAction::COVERED_UNTIL_KEY))->toBeNull();
});

it('leaves a trace when it did its work, sent or with nothing to send, and none when it could not', function (string $log, bool $sendFails, ?string $techEmail, bool $dry, bool $done) {
    // Remarque de Micha du 26/11 : « le rapport n'a pas tourne » ne doit jamais se confondre avec « il n'avait aucune
    // erreur a envoyer ». La premiere trace vient de la migration qui a pose la surveillance : on part d'une trace ancienne.
    app(SettingRepository::class)->put(SendDailyErrorReportAction::LAST_DONE_KEY, '2026-11-01T06:00:00+00:00');
    config()->set('festilaw.tech_alert_email', $techEmail);
    writeLogFile($this->logs, 'laravel-2026-11-20.log', $log);
    $sendFails ? Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail transport down.')) : Mail::fake();

    $this->artisan('festilaw:error-report', $dry ? ['--dry' => true] : []);

    expect(app(SettingRepository::class)->get(SendDailyErrorReportAction::LAST_DONE_KEY))
        ->toBe($done ? '2026-11-21T06:00:00+00:00' : '2026-11-01T06:00:00+00:00');
})->with([
    'sent' => ["[2026-11-20 10:00:00] testing.ERROR: To report. \n", false, 'tech@example.com', false, true],
    'nothing to send' => ["[2026-11-20 10:00:00] testing.WARNING: Only a warning. \n", false, 'tech@example.com', false, true],
    'failed to send' => ["[2026-11-20 10:00:00] testing.ERROR: To report. \n", true, 'tech@example.com', false, false],
    'no technical address' => ["[2026-11-20 10:00:00] testing.ERROR: To report. \n", false, null, false, false],
    'dry run' => ["[2026-11-20 10:00:00] testing.ERROR: To report. \n", false, 'tech@example.com', true, false],
]);

it('reads at most the last seven days after a long stop', function () {
    app(SettingRepository::class)->put(SendDailyErrorReportAction::COVERED_UNTIL_KEY, '2026-10-01T06:00:00+00:00');
    writeLogFile($this->logs, 'laravel.log', implode("\n", [
        '[2026-11-10 10:00:00] testing.ERROR: Older than a week. ',
        '[2026-11-18 10:00:00] testing.ERROR: Within the week. ',
        '',
    ]));
    Mail::fake();

    $this->artisan('festilaw:error-report')->assertOk();

    Mail::assertSent(DailyErrorReport::class, fn (DailyErrorReport $mail): bool => array_map(fn ($group) => $group->message, $mail->summary->groups) === ['Within the week.']);
});

it('writes a readable French report', function () {
    writeLogFile($this->logs, 'laravel-2026-11-20.log', implode("\n", [
        '[2026-11-20 08:15:00] testing.ERROR: Payment 12 could not be confirmed. ',
        '[2026-11-20 09:30:00] testing.ERROR: Payment 345 could not be confirmed. ',
        '',
    ]));
    $summary = app(ErrorLogReader::class)->read(CarbonImmutable::now()->subDay(), CarbonImmutable::now());

    $html = (new DailyErrorReport($summary))->render();

    expect($html)->toContain('Le journal du serveur compte <strong>2 erreurs</strong> du 20/11 à 07:00 au 21/11 à 07:00, heure de Paris')
        ->toContain('Erreur, 2 fois, de 09:15 à 10:30 le 20/11')
        ->toContain('Fichier laravel-2026-11-20.log');
});

it('runs every morning at a fixed UTC hour', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'festilaw:error-report'));

    expect($event?->expression)->toBe('0 6 * * *');
});

it('logs the route of an uncaught error, never its address', function () {
    Route::get('/test-boom/{token}', fn () => throw new RuntimeException('Boom'))->middleware('web')->name('test.boom');
    Log::spy();

    $this->get('/test-boom/secret-dossier-token')->assertInternalServerError();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Boom'
        && $context['route'] === 'test.boom'
        && $context['method'] === 'GET'
        && ! str_contains(json_encode(array_diff_key($context, ['exception' => true])), 'secret-dossier-token'));
});
