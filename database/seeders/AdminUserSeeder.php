<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cree le compte admin du back-office (Laetitia). Idempotent : si le compte existe deja, il n'est ni
 * recree ni ecrase (un mot de passe deja change reste intact). Le mot de passe initial "password" est
 * a changer des la premiere connexion, depuis la page "Mon compte".
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'laetitia@festilaw.com'],
            ['name' => 'Laetitia', 'password' => 'password'],
        );
    }
}
