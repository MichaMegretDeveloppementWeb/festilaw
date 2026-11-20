<?php

declare(strict_types=1);

use App\Enums\Analytics\AudienceEvent;
use Falcon\Analytics\Events\TrackedEvent;

/*
 | Le fichier qui declare les evenements du site pour Falcon Analytics (chemin par defaut du package). Les
 | noms, libelles, points et conversions s'ecrivent une seule fois, dans App\Enums\Analytics\AudienceEvent :
 | ce fichier ne fait que les lui transmettre. Une declaration vaut aussitot pour tout le detail garde.
 */

foreach (AudienceEvent::cases() as $event) {
    TrackedEvent::define($event->value, $event->label(), $event->points(), $event->isConversion());
}
