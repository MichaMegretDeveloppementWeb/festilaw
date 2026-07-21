<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Purge RGPD quotidienne des dossiers abandonnes + de leurs fichiers. Declenchee par le cron unique
 | de l'hebergement mutualise (`* * * * * php artisan schedule:run`). Pas de worker de queue : les
 | mails partent en synchrone (hebergement mutualise Hostinger, ADR-008).
 */
Schedule::command('festilaw:purge-abandoned-dossiers')->dailyAt('03:00')->withoutOverlapping();

/*
 | Renouvellements annuels : rappels client + digests admin (a renouveler / en retard). Idempotent sur
 | l'annee (anti-doublon via meta du dossier), donc sans risque a passer tous les jours.
 */
Schedule::command('festilaw:process-renewals')->dailyAt('07:00')->withoutOverlapping();

/*
 | Reconciliation des paiements : filet ultime si le retour navigateur ET le webhook sont loupes.
 | Re-interroge le provider pour les paiements en attente > 15 min et confirme les payes. Idempotent.
 */
Schedule::command('festilaw:reconcile-payments')->everyFifteenMinutes()->withoutOverlapping();

/*
 | Reconciliation des signatures : meme filet, cote signature. Re-interroge le prestataire pour les
 | contrats en attente > 15 min et enregistre signe/refuse/expire. Idempotent.
 */
Schedule::command('festilaw:reconcile-signatures')->everyFifteenMinutes()->withoutOverlapping();
