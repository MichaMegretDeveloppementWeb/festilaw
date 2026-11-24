<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Models\BlogGeneration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le formulaire d'article a recu le resultat d'une demande d'image ou de traduction (Micha, 24/11/2026) : il ne la
 * remettra plus au retour sur l'article. Les demandes plus anciennes du meme article et du meme type, deja finies,
 * le sont aussi : seule la derniere se remet, une image plus ancienne reste dans « Images déjà obtenues ». 2 requetes.
 */
final readonly class MarkBlogRequestDeliveredAction
{
    public function execute(int $generationId): void
    {
        $request = BlogGeneration::query()->find($generationId, ['id', 'type', 'source_blog_post_id']);

        if ($request === null) {
            return;
        }

        BlogGeneration::query()
            ->whereNull('delivered_at')
            ->where(fn (Builder $delivered) => $delivered->whereKey($request->id)
                ->when($request->source_blog_post_id !== null, fn (Builder $older) => $older->orWhere(fn (Builder $older) => $older
                    ->where('type', $request->type)
                    ->where('source_blog_post_id', $request->source_blog_post_id)
                    ->where('id', '<', $request->id)
                    ->whereNotIn('status', BlogGenerationStatus::activeValues()))))
            ->update(['delivered_at' => now()]);
    }
}
