<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Analytics\AudienceEvent;

/**
 * Doublure du suivi d'audience : retient les noms des faits enregistres, dans l'ordre, et la reference de visite
 * passee avec chacun, avec la meme regle « une fois par cle » que le service reel. Falcon Analytics ne fournit
 * pas de doublure a lui.
 */
final class FakeAudienceTracker implements AudienceTracker
{
    /** @var list<string> */
    public array $recorded = [];

    /** @var list<string> */
    private array $onceKeys = [];

    /** @var list<array{event: string, session: ?string}> faits passes avec leur reference de visite */
    public array $sessions = [];

    /** La reference que currentSession() rend, comme si le visiteur de la requete avait une visite en cours. */
    public ?string $currentSession = null;

    /** Remplace le suivi du conteneur par une doublure neuve, et la rend. */
    public static function bind(): self
    {
        $fake = new self;
        app()->instance(AudienceTracker::class, $fake);

        return $fake;
    }

    public function record(AudienceEvent $event, ?string $session = null): void
    {
        $this->recorded[] = $event->value;
        $this->sessions[] = ['event' => $event->value, 'session' => $session];
    }

    public function currentSession(): ?string
    {
        return $this->currentSession;
    }

    public function recordOnce(AudienceEvent $event, string $key): void
    {
        $onceKey = $event->value.'|'.$key;

        if (in_array($onceKey, $this->onceKeys, true)) {
            return;
        }

        $this->onceKeys[] = $onceKey;
        $this->record($event);
    }
}
