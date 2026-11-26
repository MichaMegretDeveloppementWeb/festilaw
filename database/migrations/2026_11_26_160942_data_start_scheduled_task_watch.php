<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration : premiere trace des deux surveillances posees le 26/11, apres le constat que la planification
     * des lots et le rapport d'erreurs du matin n'avaient jamais tourne en production. Sans trace, une tache qui ne
     * tourne jamais ne serait jamais signalee ; avec celle du deploiement, elle l'est passe le seuil (deux heures pour
     * la planification, 26 heures pour le rapport).
     *
     * Une trace deja ecrite par la tache elle-meme est gardee. Cles ecrites en clair : une data migration ne depend
     * pas d'une classe qui changera.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $keys = ['blog.ai.planner_last_run_at', 'system.error_report.last_done_at'];
            $existing = DB::table('settings')->whereIn('key', $keys)->pluck('key')->all();
            $now = now();

            $rows = array_map(static fn (string $key): array => [
                'key' => $key,
                'value' => $now->utc()->toIso8601String(),
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values(array_diff($keys, $existing)));

            if ($rows !== []) {
                DB::table('settings')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        // No-op : ces traces ne se distinguent pas de celles qu'ecrivent ensuite les taches elles-memes.
    }
};
