<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 | Purge du cache en base (audit du 23/11) : Laravel n'efface une ligne expiree que si la meme cle est relue, la
 | table grossirait sans fin entre deux deploiements.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('cache.default', 'database');
    DB::table('cache')->insert([
        ['key' => 'throttle:visitor', 'value' => '1', 'expiration' => time() - 60],
        ['key' => 'pixabay:search', 'value' => 'x', 'expiration' => time() - 3_600],
        ['key' => 'still-valid', 'value' => 'y', 'expiration' => time() + 3_600],
    ]);
    DB::table('cache_locks')->insert(['key' => 'old-lock', 'owner' => 'worker', 'expiration' => time() - 60]);
});

it('deletes the expired cache rows and locks, and keeps the valid ones', function () {
    $this->artisan('festilaw:purge-expired-cache')->assertOk();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['still-valid'])
        ->and(DB::table('cache_locks')->count())->toBe(0);
});

it('only counts in a dry run, and does nothing when the cache is not in the database', function () {
    $this->artisan('festilaw:purge-expired-cache --dry')
        ->expectsOutputToContain('2 expired entr(y/ies) and 1 expired lock(s) would be deleted')
        ->assertOk();
    expect(DB::table('cache')->count())->toBe(3);

    config()->set('cache.default', 'array');
    $this->artisan('festilaw:purge-expired-cache')->assertOk();
    expect(DB::table('cache')->count())->toBe(3);
});
