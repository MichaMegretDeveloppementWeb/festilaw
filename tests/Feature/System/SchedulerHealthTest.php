<?php

declare(strict_types=1);

use App\Http\Middleware\AlertOnStalledScheduler;
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
 | passage, bandeau dans le back-office et e-mail au prestataire technique (au plus toutes les 6 heures).
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

it('alerts the technical contact once every 6 hours while the cron is stalled', function () {
    heartbeatMinutesAgo(20);
    $health = app(SchedulerHealthService::class);

    $health->alertIfStalled();
    $health->alertIfStalled();
    Mail::assertSent(SchedulerStalledAlert::class, 1);
    Mail::assertSent(SchedulerStalledAlert::class, fn (SchedulerStalledAlert $mail) => $mail->hasTo('tech@example.com')
        && str_contains($mail->render(), '05/10/2026 à 11:40'));

    $this->travel(6)->hours();
    $this->travel(1)->minutes();
    $health->alertIfStalled();
    Mail::assertSent(SchedulerStalledAlert::class, 2);
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
        ->assertSee('Les tâches automatiques ne tournent plus depuis le 05/10/2026 à 11:30 (UTC)')
        ->assertSee('Tâches automatiques à l&#039;arrêt', false);
});

it('shows the last passage in the back-office while the cron runs, and nothing outside production', function () {
    heartbeatMinutesAgo(1);
    $admin = User::factory()->create();

    actingAs($admin)->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertDontSee('Tâches automatiques');

    $this->app['env'] = 'production';
    actingAs($admin)->get(route('admin.submissions.index'))
        ->assertOk()
        ->assertSee('Tâches automatiques · il y a 1 min')
        ->assertDontSee('ne tournent plus');
});
