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

    /**
     * Plusieurs reglages en une requete. Meme defense que get() : une table illisible rend des valeurs nulles.
     *
     * @param  list<string>  $keys
     * @return array<string, ?string> chaque cle demandee, nulle quand elle n'est pas en base
     */
    public function many(array $keys): array
    {
        $values = array_fill_keys($keys, null);

        try {
            foreach (Setting::query()->whereIn('key', $keys)->pluck('value', 'key') as $key => $value) {
                $values[(string) $key] = $value === null ? null : (string) $value;
            }
        } catch (Throwable) {
            // valeurs nulles : l'appelant retombe sur ses valeurs par defaut
        }

        return $values;
    }

    public function put(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
