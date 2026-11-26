<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Data\Blog\Generation\BlogAiSettings;
use App\Repositories\Blog\ScheduledBlogGenerationRepository;
use Carbon\CarbonImmutable;

/**
 * Le calendrier de la generation automatique : quel lot est du, et quand partira le prochain. Le planificateur
 * (PlanScheduledBlogGenerationsAction), le tableau de bord, les reglages et la verification quotidienne lisent la
 * meme regle ici, pour que la date annoncee a Laetitia ne s'ecarte jamais de celle du lot (decision du 23/11).
 *
 * Depuis le 26/11, les lots suivent des creneaux fixes, au jour et a l'heure choisis (BlogGenerationSlots). Un
 * creneau est du tant qu'aucun lot n'a ete lance depuis : un lot retenu par la pause des brouillons part des la fin
 * de la pause, et le suivant garde sa date. Plusieurs creneaux manques ne donnent qu'un lot. Une requete.
 */
final readonly class BlogGenerationCalendar
{
    public function __construct(
        private BlogGenerationSlots $slots,
        private ScheduledBlogGenerationRepository $scheduled,
    ) {}

    /** Le creneau passe qui attend encore son lot, ou nul : generation desactivee, rien de du, lot deja lance. */
    public function dueSlot(BlogAiSettings $settings, CarbonImmutable $now): ?CarbonImmutable
    {
        if (! $settings->enabled) {
            return null;
        }

        $slot = $this->slots->currentAt($settings, $now);

        if ($slot === null) {
            return null;
        }

        $lastLaunchedAt = $this->scheduled->lastLaunchedAt();

        return $lastLaunchedAt === null || $lastLaunchedAt->lessThan($slot) ? $slot : null;
    }

    /**
     * Quand partira le prochain lot : a la prochaine heure pile si un lot est du (la planification passe chaque
     * heure pile), au prochain creneau sinon.
     */
    public function nextLaunchAt(BlogAiSettings $settings, CarbonImmutable $now): CarbonImmutable
    {
        if ($this->dueSlot($settings, $now) !== null) {
            return $now->equalTo($now->startOfHour()) ? $now : $now->startOfHour()->addHour();
        }

        return $this->slots->nextAfter($settings, $now);
    }

    /** Le creneau du depuis plus de `$minutes` sans que son lot soit parti, ou nul. */
    public function lateSlot(BlogAiSettings $settings, CarbonImmutable $now, int $minutes): ?CarbonImmutable
    {
        $slot = $this->dueSlot($settings, $now);

        return $slot !== null && $slot->lessThan($now->subMinutes($minutes)) ? $slot : null;
    }
}
