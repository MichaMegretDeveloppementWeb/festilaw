<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Setting;
use Throwable;

/**
 * Read/write access to key/value application settings. Reads are defensive : if the table is not
 * available (e.g. before the migration runs, or a degraded context), get() returns null so callers fall
 * back to their config default rather than crashing.
 */
final class SettingRepository
{
    public function get(string $key): ?string
    {
        try {
            $value = Setting::query()->where('key', $key)->value('value');

            return $value === null ? null : (string) $value;
        } catch (Throwable) {
            return null;
        }
    }

    public function put(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
