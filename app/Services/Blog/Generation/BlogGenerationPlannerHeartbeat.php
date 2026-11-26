<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * La trace de chaque passage de la planification des lots (festilaw:blog-generation-schedule, chaque heure). Le
 * 26/11, la planification n'avait jamais tourne en production sans que rien le signale : la verification
 * quotidienne lit desormais cette trace (BlogGenerationHealthCheck). Dans la table settings, comme le battement
 * du cron (SchedulerHealthService), jamais dans le cache que vide deploy.sh.
 */
final readonly class BlogGenerationPlannerHeartbeat
{
    public const KEY = 'blog.ai.planner_last_run_at';

    public function __construct(private SettingRepository $settings) {}

    public function record(CarbonImmutable $at): void
    {
        $this->settings->put(self::KEY, $at->utc()->toIso8601String());
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        $value = $this->settings->get(self::KEY);

        try {
            return $value === null ? null : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
