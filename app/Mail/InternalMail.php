<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * Un e-mail lu par l'equipe de Festilaw ou par le prestataire technique, jamais par un client : il part toujours
 * en francais (decision de Micha du 23/11). Sans cela, il prenait la langue de l'application, l'anglais quand il
 * part du worker ou d'une commande, ou celle du visiteur dont le formulaire le declenche, et son pied de page et
 * ses libelles changeaient de langue autour d'un texte francais.
 */
abstract class InternalMail extends Mailable
{
    /** @var string */
    public $locale = 'fr';
}
