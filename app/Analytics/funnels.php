<?php

declare(strict_types=1);

use App\Enums\Analytics\AudienceEvent;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelBranch;

/*
 | Le fichier qui declare les tunnels du site pour Falcon Analytics (plan SC8 approuve par Micha le 20/11).
 | Une etape vaut 0 point, sauf l'etape payee, qui vaut les points de son evenement : le score d'un tunnel se
 | lit en chiffre d'affaires, comme celui des conversions. Un tunnel ne suit que ce qu'une personne fait
 | d'une traite : sans consentement, un visiteur vit le temps de sa session.
 |
 | Une etape se franchit par un evenement ou une page vue ; les pages traduites ont une route par langue,
 | d'ou une branche par langue.
 */

$offerChosen = [
    FunnelBranch::event('Creator Pack', AudienceEvent::CreatorChosen->value),
    FunnelBranch::event('Pro Pack', AudienceEvent::ProChosen->value),
    FunnelBranch::event('Scale Pack', AudienceEvent::ScaleChosen->value),
];

$dossierOrRequest = [
    FunnelBranch::event('Dossier Creator Pack', AudienceEvent::CreatorOpened->value),
    FunnelBranch::event('Dossier Pro Pack', AudienceEvent::ProOpened->value),
    FunnelBranch::event('Demande Scale Pack', AudienceEvent::ScaleRequested->value),
];

foreach ([
    'creator' => ['Creator Pack', 'get-started.starter', AudienceEvent::CreatorOpened, AudienceEvent::CreatorPaid],
    'pro' => ['Pro Pack', 'get-started.pro', AudienceEvent::ProOpened, AudienceEvent::ProPaid],
] as $key => [$pack, $formRoute, $opened, $paid]) {
    Funnel::define("subscription_{$key}", "Souscription {$pack}")
        ->step("Formulaire {$pack}", value: 0, route: $formRoute)
        ->step('Dossier ouvert', value: 0, event: $opened->value)
        ->step('Documents envoyés', value: 0, event: AudienceEvent::DocumentsSent->value)
        ->step('Contrat signé', value: 0, event: AudienceEvent::ContractSigned->value)
        ->step('Paiement ouvert', value: 0, event: AudienceEvent::PaymentOpened->value)
        ->step("{$pack} payé", value: (int) $paid->points(), event: $paid->value);
}

Funnel::define('scale_request', 'Demande Scale Pack')
    ->step('Page Scale Pack', value: 0, route: 'get-started.scale')
    ->step('Demande envoyée', value: 0, event: AudienceEvent::ScaleRequested->value)
    ->step('Audit payé', value: (int) AudienceEvent::ScaleAuditPaid->points(), event: AudienceEvent::ScaleAuditPaid->value)
    ->step('Rendez-vous demandé', value: 0, event: AudienceEvent::ScaleBooked->value);

Funnel::define('offers_to_dossier', 'Des offres au dossier')
    ->step('Offres consultées', value: 0, anyOf: [
        FunnelBranch::route('Tarifs, anglais', 'pricing'),
        FunnelBranch::route('Tarifs, français', 'fr.pricing'),
        FunnelBranch::route('Tarifs, espagnol', 'es.pricing'),
        FunnelBranch::route('Page de démarrage', 'get-started.index'),
    ])
    ->step('Formule choisie', value: 0, anyOf: $offerChosen)
    ->step('Dossier ou demande', value: 0, anyOf: $dossierOrRequest);

Funnel::define('quiz_to_dossier', 'Du quiz au dossier')
    ->step('Quiz commencé', value: 0, event: AudienceEvent::QuizAnswered->value)
    ->step('Quiz terminé', value: 0, anyOf: [
        FunnelBranch::event('Concerné', AudienceEvent::QuizConcerned->value),
        FunnelBranch::event('Catégorie exclue', AudienceEvent::QuizExcluded->value),
        FunnelBranch::event('Non concerné', AudienceEvent::QuizNotConcerned->value),
    ])
    ->step('Formule choisie', value: 0, anyOf: $offerChosen)
    ->step('Dossier ou demande', value: 0, anyOf: $dossierOrRequest);

Funnel::define('blog_to_dossier', 'Du blog au dossier')
    ->step('Article lu', value: 0, anyOf: [
        FunnelBranch::route('Article en anglais', 'blog.show'),
        FunnelBranch::route('Article en français', 'fr.blog.show'),
        FunnelBranch::route('Article en espagnol', 'es.blog.show'),
    ])
    ->step('Formule choisie', value: 0, anyOf: $offerChosen)
    ->step('Dossier ou demande', value: 0, anyOf: $dossierOrRequest);
