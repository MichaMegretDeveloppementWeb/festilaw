<?php

declare(strict_types=1);

use App\Actions\System\SendDailyErrorReportAction;
use App\Http\Middleware\AlertOnStalledScheduler;
use App\Mail\ErrorReportMissingAlert;
use App\Mail\SchedulerStalledAlert;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\System\SchedulerHealthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;
use function Pest\Laravel\actingAs;

/*
 | Surveillance des taches automatiques : le cron note son passage chaque minute ; apres 15 minutes sans
 | passage, bandeau dans le back-office et e-mail au prestataire technique (une seule fois par arret).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
    config()->set('festilaw.tech_alert_email', 'tech@example.com');
    config()->set('festilaw.notification_email', 'team@example.com');
    Cache::flush();
    Mail::fake();
});

function heartbeatMinutesAgo(int $minutes): void
{
    app(SettingRepository::class)->put(SchedulerHealthService::HEARTBEAT_KEY, now()->subMinutes($minutes)->toIso8601String());
}

it('records the cron passage, every minute', function () {
    $this->artisan('festilaw:scheduler-heartbeat')->assertOk();

    expect(app(SchedulerHealthService::class)->lastRunAt()?->equalTo(now()))->toBeTrue();

    $heartbeat = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'festilaw:scheduler-heartbeat'));
    expect($heartbeat)->not->toBeNull()
        ->and($heartbeat->expression)->toBe('* * * * *');
});

it('considers the automatic tasks stalled only after 15 minutes without a passage', function () {
    $health = app(SchedulerHealthService::class);
    expect($health->isStalled())->toBeFalse(); // aucun passage encore : installation neuve

    heartbeatMinutesAgo(10);
    expect($health->isStalled())->toBeFalse();

    heartbeatMinutesAgo(16);
    expect($health->isStalled())->toBeTrue();
});

it('alerts the technical contact once per stall, at the time of Paris, and again only after the cron came back', function () {
    // Decision de Micha du 23/11 : une seule alerte par arret, a l'heure de Paris comme le rapport du matin.
    heartbeatMinutesAgo(20);
    $health = app(SchedulerHealthService::class);

    $health->alertIfStalled();
    $health->alertIfStalled();
    Mail::assertSent(SchedulerStalledAlert::class, 1);
    Mail::assertSent(SchedulerStalledAlert::class, fn (SchedulerStalledAlert $mail) => $mail->hasTo('tech@example.com')
        && str_contains($mail->render(), '05/10/2026 à 13:40</strong>, heure de Paris'));

    $this->travel(2)->days();
    $health->alertIfStalled();
    Mail::assertSent(SchedulerStalledAlert::class, 1);

    // Le cron reprend, puis s'arrete de nouveau : c'est un autre arret.
    heartbeatMinutesAgo(20);
    $health->alertIfStalled();
    Mail::assertSent(SchedulerStalledAlert::class, 2);
});

it('alerts once when the morning report has done nothing for 26 hours, and again only after it came back', function () {
    // Constat du 26/11 : le rapport d'erreurs n'avait jamais tourne en production, sans que personne le sache.
    heartbeatMinutesAgo(1);
    $health = app(SchedulerHealthService::class);
    $reportDoneHoursAgo = fn (int $hours) => app(SettingRepository::class)
        ->put(SendDailyErrorReportAction::LAST_DONE_KEY, now()->subHours($hours)->toIso8601String());

    $reportDoneHoursAgo(25);
    $health->alertIfStopped();
    Mail::assertNothingSent();

    $reportDoneHoursAgo(27);
    $health->alertIfStopped();
    $health->alertIfStopped();
    Mail::assertSent(ErrorReportMissingAlert::class, 1);
    Mail::assertSent(ErrorReportMissingAlert::class, fn (ErrorReportMissingAlert $mail) => $mail->hasTo('tech@example.com')
        && str_contains($mail->render(), '04/10/2026 à 11:00</strong>, heure de Paris'));

    // Le rapport reprend, puis s'arrete de nouveau : c'est un autre arret.
    $this->travel(3)->days();
    heartbeatMinutesAgo(1);
    $reportDoneHoursAgo(30);
    $health->alertIfStopped();
    Mail::assertSent(ErrorReportMissingAlert::class, 2);
});

it('leaves the morning report out while the cron itself is stopped, the stalled tasks alert covering it', function () {
    heartbeatMinutesAgo(20);
    app(SettingRepository::class)->put(SendDailyErrorReportAction::LAST_DONE_KEY, now()->subHours(30)->toIso8601String());

    app(SchedulerHealthService::class)->alertIfStopped();

    Mail::assertSent(SchedulerStalledAlert::class, 1);
    Mail::assertNotSent(ErrorReportMissingAlert::class);
});

it('sends nothing while the cron runs', function () {
    heartbeatMinutesAgo(1);

    app(SchedulerHealthService::class)->alertIfStalled();

    Mail::assertNothingSent();
});

it('falls back on the team address when no technical contact is set', function () {
    config()->set('festilaw.tech_alert_email', null);
    heartbeatMinutesAgo(20);

    app(SchedulerHealthService::class)->alertIfStalled();

    Mail::assertSent(SchedulerStalledAlert::class, fn (SchedulerStalledAlert $mail) => $mail->hasTo('team@example.com'));
});

it('never fails when the alert cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    heartbeatMinutesAgo(20);

    app(SchedulerHealthService::class)->alertIfStalled();
})->throwsNoExceptions();

it('lets a visit raise the alert after the response, in production only', function () {
    heartbeatMinutesAgo(20);
    $middleware = app(AlertOnStalledScheduler::class);

    $middleware->handle(Request::create('/'), fn () => response('ok'));
    defer()->invoke();
    Mail::assertNothingSent(); // hors production

    $this->app['env'] = 'production';
    $response = $middleware->handle(Request::create('/'), fn () => response('ok'));
    expect($response->getContent())->toBe('ok');
    Mail::assertNothingSent(); // rien avant la fin de la reponse

    defer()->invoke();
    Mail::assertSent(SchedulerStalledAlert::class, 1);
});

it('shows the stalled tasks in the back-office, in production', function () {
    $this->app['env'] = 'production';
    heartbeatMinutesAgo(30);

    actingAs(User::factory()->create())
        ->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertSee('Les relances et la génération automatique sont à l&#039;arrêt depuis le 05/10/2026 à 11:30 (UTC)', false)
        ->assertSee('L&#039;assistance technique est prévenue par e-mail.', false)
        // Audit du 23/11 : ni pastille d'etat technique ni vocabulaire du serveur a l'ecran de Laetitia.
        ->assertDontSee('Tâches automatiques')
        ->assertDontSee('cron');
});

it('says nothing about the cron in the back-office while it runs, in production or not', function () {
    heartbeatMinutesAgo(1);
    $admin = User::factory()->create();

    actingAs($admin)->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertDontSee('Tâches automatiques');

    $this->app['env'] = 'production';
    actingAs($admin)->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertDontSee('Tâches automatiques')
        ->assertDontSee('à l&#039;arrêt depuis', false);
});
