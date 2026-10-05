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
 | Politique de confidentialite : acces ferme des dossiers annules (rattrapage / filet) et suppression des
 | demandes de contact sans echange depuis 12 mois. Idempotent.
 */
Schedule::command('festilaw:apply-privacy-retention')->dailyAt('03:15')->withoutOverlapping();

/*
 | Renouvellements annuels : rappels client + digests admin (a renouveler / en retard). Idempotent sur
 | l'annee (anti-doublon via meta du dossier), donc sans risque a passer tous les jours.
 */
Schedule::command('festilaw:process-renewals')->dailyAt('07:00')->withoutOverlapping();

/*
 | Reconciliation des paiements : filet ultime si le retour navigateur ET le webhook sont loupes.
 | Re-interroge le provider pour les paiements en attente > 15 min et confirme les payes. Idempotent.
 | Toutes les 5 min : rattrapage rapide sans marteler l'API du provider (garde le seuil interne > 15 min).
 */
Schedule::command('festilaw:reconcile-payments')->everyFiveMinutes()->withoutOverlapping();

/*
 | Reconciliation des signatures : meme filet, cote signature. Re-interroge le prestataire pour les
 | contrats en attente > 15 min et enregistre signe/refuse/expire. Toutes les 5 min. Idempotent.
 */
Schedule::command('festilaw:reconcile-signatures')->everyFiveMinutes()->withoutOverlapping();

/*
 | PDF du mandat signe : avec la signature integree, le navigateur confirme la signature avant que le
 | prestataire ait genere le PDF final. Le webhook le recupere quand il arrive ; a defaut, ce rattrapage
 | le telecharge dans la minute (requete legere : contrats signes sans fichier). Idempotent.
 */
Schedule::command('festilaw:backfill-signed-pdfs')->everyMinute()->withoutOverlapping();
