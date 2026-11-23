<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Repositories\Blog\ScheduledBlogGenerationRepository;
use Carbon\CarbonImmutable;

/**
 * Le calendrier de la generation automatique : quand le prochain lot est du, et a quel controle horaire il
 * partira. Le planificateur (PlanScheduledBlogGenerationsAction, routes/console.php) et le tableau de bord
 * lisent la meme regle ici, pour que la date annoncee a Laetitia ne s'ecarte jamais de celle du lot (decision
 * du 23/11). Une requete, deux pour un rythme mensuel.
 */
final readonly class BlogGenerationCalendar
{
    /** Le controle « un lot est-il du ? » passe chaque heure pile, de 6 h a 20 h UTC (routes/console.php). */
    public const FIRST_HOUR = 6;

    public const LAST_HOUR = 20;

    public function __construct(private ScheduledBlogGenerationRepository $scheduled) {}

    /**
     * Le jour ou le prochain lot est du : la periode reglee apres le dernier lot lance. Nul tant qu'aucun lot n'a
     * ete lance, le premier est du aussitot. Un rythme mensuel garde le jour du premier lot apres un mois court.
     */
    public function dueAt(BlogAiSettings $settings): ?CarbonImmutable
    {
        $lastLaunchedAt = $this->scheduled->lastLaunchedAt();

        if ($lastLaunchedAt === null) {
            return null;
        }

        $anchorDay = $settings->periodUnit === BlogAiPeriodUnit::Month ? $this->scheduled->firstLaunchedAt()?->day : null;

        return $settings->periodUnit->addTo($lastLaunchedAt, $settings->periodCount, $anchorDay)->startOfDay();
    }

    /** Le controle horaire qui lancera le prochain lot : a l'heure pile, entre 6 h et 20 h UTC. */
    public function nextLaunchAt(BlogAiSettings $settings, CarbonImmutable $now): CarbonImmutable
    {
        $due = $this->dueAt($settings);
        $from = $due === null || $due->lessThan($now) ? $now : $due;
        $tick = $from->equalTo($from->startOfHour()) ? $from : $from->startOfHour()->addHour();

        if ($tick->hour < self::FIRST_HOUR) {
            return $tick->setTime(self::FIRST_HOUR, 0);
        }

        return $tick->hour > self::LAST_HOUR ? $tick->addDay()->setTime(self::FIRST_HOUR, 0) : $tick;
    }
}
