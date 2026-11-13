<?php

declare(strict_types=1);

use App\Actions\Admin\SendAdminMessageAction;
use App\Mail\AdminMessageToClient;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

/*
 | Langue de repli : un dossier sans langue enregistree est servi dans la langue PAR DEFAUT du site
 | (config('festilaw.default_locale')), jamais dans la langue courante. config('app.locale') est reecrite
 | par Laravel a chaque setLocale() : s'en servir comme repli envoyait l'e-mail du client dans la langue
 | de la session de la personne qui agit (corrige le 2026-11-13, 11 endroits).
 */

uses(RefreshDatabase::class);

it('writes to a client whose dossier has no language in the default language of the site, not in the current one', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create(['locale' => '']);

    // La personne qui agit navigue en francais : la langue courante n'est pas la langue par defaut.
    app()->setLocale('fr');

    app(SendAdminMessageAction::class)->execute($submission, 'Subject', 'Body');

    Mail::assertSent(AdminMessageToClient::class, fn (AdminMessageToClient $mail): bool => $mail->locale === config('festilaw.default_locale'));

    expect(config('festilaw.default_locale'))->not->toBe('fr');
});

it('still writes to a client in the language of the dossier when it has one', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create(['locale' => 'es']);

    app()->setLocale('fr');

    app(SendAdminMessageAction::class)->execute($submission, 'Subject', 'Body');

    Mail::assertSent(AdminMessageToClient::class, fn (AdminMessageToClient $mail): bool => $mail->locale === 'es');
});

it('never reads the current language as if it were the default one', function () {
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            $code = ltrim($line);

            // Les commentaires qui expliquent la regle citent l'appel : ils ne comptent pas.
            if (str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*')) {
                continue;
            }

            if (preg_match('/config\(\s*[\'"]app\.locale[\'"]\s*\)/', $code) === 1) {
                $offenders[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
