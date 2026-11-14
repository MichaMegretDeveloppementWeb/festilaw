<?php

declare(strict_types=1);

namespace App\Repositories\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le verrou d'une generation : qui la traite, et jusqu'a quand. Chaque operation est une seule mise a jour
 * conditionnelle, donc atomique : deux workers ne peuvent pas prendre la meme generation. Le verrou a une
 * duree courte et expire seul si son worker meurt.
 */
final readonly class BlogGenerationLockRepository
{
    /** Prend la generation si elle est encore a traiter et libre. Faux : un autre worker l'a prise. */
    public function claim(int $generationId, string $owner, CarbonImmutable $now, CarbonImmutable $until): bool
    {
        $claimed = BlogGeneration::query()
            ->whereKey($generationId)
            ->whereIn('status', BlogGenerationStatus::activeValues())
            ->where(static function (Builder $query) use ($now): void {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now);
            })
            ->update([
                'status' => BlogGenerationStatus::Running->value,
                'locked_until' => $until,
                'lock_owner' => $owner,
            ]) === 1;

        if ($claimed) {
            BlogGeneration::query()->whereKey($generationId)->whereNull('started_at')->update(['started_at' => $now]);
        }

        return $claimed;
    }

    /**
     * Prolonge le verrou. Faux : ce worker ne le detient plus (un autre a repris la generation).
     *
     * La detention se lit, elle ne se deduit pas du nombre de lignes de la mise a jour : MySQL compte les
     * lignes MODIFIEES, et prolonger deux fois dans la meme seconde n'en modifie aucune (SQLite, lui, compte
     * les lignes trouvees : les tests ne le voient pas).
     */
    public function extend(int $generationId, string $owner, CarbonImmutable $until): bool
    {
        $held = static fn (): Builder => BlogGeneration::query()
            ->whereKey($generationId)
            ->where('lock_owner', $owner)
            ->where('status', BlogGenerationStatus::Running->value);

        if (! $held()->exists()) {
            return false;
        }

        $held()->update(['locked_until' => $until]);

        return true;
    }

    /** Rend la generation sans la terminer : elle sera reprise au passage suivant d'un worker. */
    public function release(int $generationId, string $owner): void
    {
        BlogGeneration::query()
            ->whereKey($generationId)
            ->where('lock_owner', $owner)
            ->update(['locked_until' => null, 'lock_owner' => null]);
    }
}
