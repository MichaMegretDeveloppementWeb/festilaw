<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration : rattache les demandes deja faites et leurs images a leur article, apres
     * add_request_links_to_blog_generations_and_blog_images (retour de Micha du 24/11/2026). Sans elle, l'image
     * payee en production depuis un brouillon partait a la purge des images sans article.
     *
     * - Chaque demande d'image ou de traduction prend l'article de sa charge, s'il existe encore.
     * - L'image principale obtenue par chaque generation terminee (article ou image) est reliee a cette generation,
     *   d'apres la sortie de son etape `images` : elle reste a l'article meme remplacee.
     * - Les demandes deja terminees sont marquees remises : rien ne surgit au retour sur un article, l'image se
     *   retrouve dans « Images déjà obtenues ».
     *
     * Valeurs ecrites en clair (types, statuts, etape) : une data migration ne depend pas d'un enum qui changera.
     * Quelques requetes en tout, aucune dans une boucle ; non destructive.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->linkRequestsToTheirArticle();
            $this->linkMainImagesToTheirGeneration();

            DB::table('blog_generations')
                ->whereIn('type', ['image', 'translation'])
                ->whereIn('status', ['completed', 'abandoned', 'failed', 'cancelled'])
                ->whereNull('delivered_at')
                ->update(['delivered_at' => DB::raw('COALESCE(finished_at, updated_at)')]);
        });
    }

    public function down(): void
    {
        // No-op : les liens poses ici ne se distinguent pas de ceux poses ensuite par l'application.
    }

    private function linkRequestsToTheirArticle(): void
    {
        $wanted = [];

        foreach (DB::table('blog_generations')->whereIn('type', ['image', 'translation'])->whereNull('source_blog_post_id')->get(['id', 'type', 'payload']) as $request) {
            $payload = json_decode((string) $request->payload, true);
            $postId = $request->type === 'image' ? ($payload['image_request']['post_id'] ?? null) : ($payload['translation']['post_id'] ?? null);

            if (is_numeric($postId)) {
                $wanted[(int) $request->id] = (int) $postId;
            }
        }

        $existing = $wanted === [] ? [] : DB::table('blog_posts')->whereIn('id', array_values(array_unique($wanted)))->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        $this->updateByCase('blog_generations', 'source_blog_post_id', array_filter($wanted, static fn (int $postId): bool => in_array($postId, $existing, true)));
    }

    private function linkMainImagesToTheirGeneration(): void
    {
        $links = [];

        foreach (DB::table('blog_generation_steps')->where('step', 'images')->where('status', 'completed')->get(['blog_generation_id', 'output']) as $step) {
            $output = json_decode((string) $step->output, true);

            foreach ((array) ($output['images'] ?? []) as $image) {
                if (is_array($image) && (int) ($image['number'] ?? -1) === 0 && is_numeric($image['image_id'] ?? null)) {
                    $links[(int) $image['image_id']] = (int) $step->blog_generation_id;
                }
            }
        }

        $this->updateByCase('blog_images', 'generation_id', $links);
    }

    /**
     * Une seule requete pour toutes les lignes : `CASE id WHEN … THEN … END`. Cles et valeurs sont des entiers lus
     * en base, ecrits tels quels.
     *
     * @param  array<int, int>  $values  valeur par identifiant de ligne
     */
    private function updateByCase(string $table, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }

        $cases = implode(' ', array_map(static fn (int $id, int $value): string => "WHEN {$id} THEN {$value}", array_keys($values), $values));

        DB::table($table)->whereIn('id', array_keys($values))->whereNull($column)->update([$column => DB::raw("CASE id {$cases} END")]);
    }
};
