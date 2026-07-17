<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds the admin account', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'laetitia@festilaw.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Laetitia');
    expect(Hash::check('password', $admin->password))->toBeTrue();
});

it('is idempotent and never overwrites a changed password', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'laetitia@festilaw.com')->first();
    $admin->password = 'a-new-password';
    $admin->save();

    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'laetitia@festilaw.com')->count())->toBe(1);
    expect(Hash::check('a-new-password', $admin->fresh()->password))->toBeTrue();
});
