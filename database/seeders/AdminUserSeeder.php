<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Cree le compte admin du back-office, sans ecraser un compte deja present. */
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
