<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration : pose le point de depart du calendrier de la generation automatique (decision de Micha du
     * 26/11) dans des reglages deja enregistres. Le calendrier compte alors a partir du deploiement : le premier lot
     * part au premier creneau qui suit (jour et heure de depart, mardi 9 h, tant qu'ils ne sont pas changes). Sans
     * point de depart, aucun creneau ne serait jamais du.
     *
     * Rien a faire sans reglages enregistres (valeurs de depart, generation eteinte) ni quand le point de depart
     * existe deja. Cle et forme ecrites en clair : une data migration ne depend pas d'une classe qui changera.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $row = DB::table('settings')->where('key', 'blog.ai')->lockForUpdate()->first(['id', 'value']);
            $stored = $row === null ? null : json_decode((string) $row->value, true);

            if (! is_array($stored) || ! empty($stored['starts_at'])) {
                return;
            }

            $stored['starts_at'] = now()->utc()->toIso8601String();

            DB::table('settings')->where('id', $row->id)->update([
                'value' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // No-op : le point de depart pose ici ne se distingue pas de celui que pose ensuite l'enregistrement des reglages.
    }
};
