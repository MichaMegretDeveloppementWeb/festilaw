<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Cree (ou met a jour) un compte administrateur du back-office. Il n'y a pas d'inscription publique :
 * les comptes de l'equipe Festilaw sont crees avec cette commande. Le mot de passe est hache par le
 * cast du modele User.
 */
final class CreateAdminUser extends Command
{
    protected $signature = 'festilaw:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Cree ou met a jour un compte administrateur du back-office.';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: text('Nom de l\'administrateur', required: true));
        $email = (string) ($this->option('email') ?: text('Email', required: true));
        $plainPassword = (string) ($this->option('password') ?: password('Mot de passe', required: true));

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $plainPassword],
        );

        $this->info("Compte administrateur pret : {$user->email}");

        return self::SUCCESS;
    }
}
