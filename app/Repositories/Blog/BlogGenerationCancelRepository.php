<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Models\BlogGeneration;

/**
 * Une generation a-t-elle ete arretee a la main ? Lu entre deux etapes, et avant chaque appel a l'IA : une
 * etape a plusieurs appels (redaction et sa correction, photos et leur controle) ne continue pas de payer
 * apres l'arret (controle du 20/11).
 */
final readonly class BlogGenerationCancelRepository
{
    public function isRequested(int $generationId): bool
    {
        return BlogGeneration::query()->whereKey($generationId)->whereNotNull('cancel_requested_at')->exists();
    }
}
