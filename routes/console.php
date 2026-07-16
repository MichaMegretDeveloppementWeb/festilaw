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
