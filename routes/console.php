<?php

use App\Services\Blog\Generation\BlogGenerationCalendar;
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
Schedule::command('festilaw:purge-abandoned-dossiers')->dailyAt('03:00')->withoutOverlapping(60);

/*
 | Politique de confidentialite : acces ferme des dossiers annules (rattrapage / filet) et suppression des
 | demandes de contact sans echange depuis 12 mois. Idempotent.
 */
Schedule::command('festilaw:apply-privacy-retention')->dailyAt('03:15')->withoutOverlapping(60);

/*
 | Renouvellements annuels : rappels client + digests admin (a renouveler / en retard). Idempotent sur
 | l'annee (anti-doublon via meta du dossier), donc sans risque a passer tous les jours.
 */
Schedule::command('festilaw:process-renewals')->dailyAt('07:00')->withoutOverlapping(60);

/*
 | Reconciliation des paiements : filet ultime si le retour navigateur ET le webhook sont loupes.
 | Re-interroge le provider pour les paiements en attente > 15 min et confirme les payes. Idempotent.
 | Toutes les 5 min : rattrapage rapide sans marteler l'API du provider (garde le seuil interne > 15 min).
 */
Schedule::command('festilaw:reconcile-payments')->everyFiveMinutes()->withoutOverlapping(15);

/*
 | Reconciliation des signatures : meme filet, cote signature. Re-interroge le prestataire pour les
 | contrats en attente > 15 min et enregistre signe/refuse/expire. Toutes les 5 min. Idempotent.
 */
Schedule::command('festilaw:reconcile-signatures')->everyFiveMinutes()->withoutOverlapping(15);

/*
 | PDF du mandat signe : avec la signature integree, le navigateur confirme la signature avant que le
 | prestataire ait genere le PDF final. Le webhook le recupere quand il arrive ; a defaut, ce rattrapage
 | le telecharge dans la minute (requete legere : contrats signes sans fichier). Idempotent.
 */
Schedule::command('festilaw:backfill-signed-pdfs')->everyMinute()->withoutOverlapping(10);

/*
 | Blog : publication des articles programmes dont l'heure est passee. Chaque minute, requete legere
 | (index status + scheduled_for). Idempotent. Le verrou expire au bout de 5 minutes : un passage tue
 | brutalement ne bloque pas la publication pendant 24 h (duree par defaut de withoutOverlapping).
 */
Schedule::command('festilaw:blog-publish-scheduled')->everyMinute()->withoutOverlapping(5);

/*
 | Blog : menage quotidien des images sans article (envoyees puis jamais rattachees, ou orphelines
 | depuis une suppression) et des dossiers d'image sans ligne. Idempotent.
 */
Schedule::command('festilaw:blog-purge-orphan-images')->dailyAt('03:30')->withoutOverlapping(60);

/*
 | Cache en base : efface les lignes expirees (limites de debit par visiteur, recherches de photos), que Laravel
 | ne retire que si la meme cle est relue. Sans elle, la table grossit entre deux deploiements (audit du 23/11).
 */
Schedule::command('festilaw:purge-expired-cache')->dailyAt('03:45')->withoutOverlapping(10);

/*
 | Battement : note chaque passage du cron. S'il s'arrete, le back-office l'affiche et le prestataire
 | technique est prevenu par e-mail (SchedulerHealthService, AlertOnStalledScheduler).
 */
Schedule::command('festilaw:scheduler-heartbeat')->everyMinute();

/*
 | Blog, generation par IA : verification quotidienne de ses services (cle et modeles chez OpenRouter, Pixabay,
 | file des generations, date de relecture de la reference des regles de l'UE), sans aucun appel a un modele.
 | Juste avant le rapport d'erreurs, qui en reprend chaque probleme.
 */
Schedule::command('festilaw:blog-health-check')->dailyAt('05:45')->withoutOverlapping(30);

/*
 | Rapport d'erreurs du matin : les erreurs du journal du serveur depuis le dernier rapport, au prestataire
 | technique, rien quand il n'y en a pas. A heure fixe en UTC (7 h a Paris l'hiver, 8 h l'ete) : planifiee
 | dans un fuseau a heure d'ete, une tache peut tourner deux fois ou pas du tout. Rejouable.
 */
Schedule::command('festilaw:error-report')->dailyAt('06:00')->withoutOverlapping(30);

/*
 | Blog, generation par IA : met en file le lot d'articles quand la periode reglee au back-office est echue.
 | Elle ne genere rien elle-meme (c'est le worker ci-dessous). Chaque heure de 6 h a 20 h (UTC) : rejouable, elle
 | ne lance qu'un lot par periode, et une heure manquee ne coute plus un jour (controle du 20/11).
 */
Schedule::command('festilaw:blog-generation-schedule')
    ->hourly()
    // La fenetre vient du calendrier que lit aussi le tableau de bord, pour annoncer la date du prochain lot.
    ->between(sprintf('%02d:00', BlogGenerationCalendar::FIRST_HOUR), sprintf('%02d:00', BlogGenerationCalendar::LAST_HOUR))
    ->withoutOverlapping(10);

/*
 | Blog, generation par IA : worker. EN DERNIER, parce qu'il ne rend pas la main : il reste a l'ecoute
 | environ une minute (toutes les deux secondes : « une generation a traiter ? ») et, s'il en prend une, le
 | temps de la traiter. Le planificateur en relance un chaque minute ; le precedent s'arrete quand le
 | nouveau s'est inscrit, si bien qu'il y en a toujours un a l'ecoute. Pas de withoutOverlapping : ce
 | relais et le verrou de chaque generation (blog_generations.locked_until) en tiennent lieu.
 */
Schedule::command('festilaw:blog-generation-worker')->everyMinute();
