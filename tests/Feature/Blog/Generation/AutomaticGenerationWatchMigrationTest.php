<?php

use App\Actions\System\SendDailyErrorReportAction;
use App\Models\Setting;
use App\Repositories\SettingRepository;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationPlannerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Migrations de donnees du 26/11/2026 : le calendrier de la generation deja activee en production compte a partir
 | du deploiement, et la planification comme le rapport du matin recoivent une premiere trace, pour qu'une tache qui
 | ne tourne jamais finisse par etre signalee.
 */

uses(RefreshDatabase::class);

function runCalendarStart(): void
{
    (require database_path('migrations/2026_11_26_160941_data_start_blog_generation_calendar.php'))->up();
}

function runTaskWatchStart(): void
{
    (require database_path('migrations/2026_11_26_160942_data_start_scheduled_task_watch.php'))->up();
}

it('starts the calendar of saved settings from the deployment, keeping everything else', function () {
    $this->travelTo('2026-11-27 10:00:00');
    $repository = app(SettingRepository::class);
    $repository->put(BlogAiSettingsService::KEY, (string) json_encode(['enabled' => true, 'period_unit' => 'week', 'articles_per_run' => 2]));

    runCalendarStart();
    $this->travelTo('2026-11-28 10:00:00');
    runCalendarStart();

    expect(json_decode((string) $repository->get(BlogAiSettingsService::KEY), true))->toBe([
        'enabled' => true, 'period_unit' => 'week', 'articles_per_run' => 2, 'starts_at' => '2026-11-27T10:00:00+00:00',
    ]);
});

it('writes nothing when no settings were ever saved', function () {
    runCalendarStart();

    expect(app(SettingRepository::class)->get(BlogAiSettingsService::KEY))->toBeNull();
});

it('gives the planner and the morning report a first trace, without overwriting one they wrote', function () {
    $repository = app(SettingRepository::class);
    // La base des tests a deja passe ces migrations : on repart sans trace pour la planification.
    Setting::query()->where('key', BlogGenerationPlannerHeartbeat::KEY)->delete();
    $repository->put(SendDailyErrorReportAction::LAST_DONE_KEY, '2026-11-26T06:00:05+00:00');
    $this->travelTo('2026-11-27 10:00:00');

    runTaskWatchStart();

    expect($repository->get(BlogGenerationPlannerHeartbeat::KEY))->toBe('2026-11-27T10:00:00+00:00')
        ->and($repository->get(SendDailyErrorReportAction::LAST_DONE_KEY))->toBe('2026-11-26T06:00:05+00:00');
});
