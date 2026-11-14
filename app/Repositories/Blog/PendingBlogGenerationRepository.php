<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * La file d'attente des generations, lue par le worker : « que reste-t-il a traiter ? ». Une requete
 * indexee (status, locked_until), lancee toutes les deux secondes : elle doit rester legere.
 */
final readonly class PendingBlogGenerationRepository
{
    /**
     * La plus ancienne generation a traiter : en attente, ou en cours avec un verrou expire (son worker
     * est mort). Rien tant qu'une autre generation tourne sous un verrou valable : une seule a la fois.
     */
    public function nextId(CarbonImmutable $now): ?int
    {
        $id = BlogGeneration::query()
            ->whereIn('status', BlogGenerationStatus::activeValues())
            ->where(static function (Builder $query) use ($now): void {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now);
            })
            ->whereNotExists(static function (QueryBuilder $query) use ($now): void {
                $query->selectRaw('1')
                    ->from('blog_generations as live')
                    ->where('live.status', BlogGenerationStatus::Running->value)
                    ->where('live.locked_until', '>', $now);
            })
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
