<?php

declare(strict_types=1);

namespace App\Services\Blog\Slug;

use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Repositories\Blog\BlogPostSlugRepository;

/**
 * Decide le slug d'un article dans une langue, puis tient l'historique des anciens slugs. Appele
 * dans la transaction de l'Action qui enregistre ou supprime l'article : n'ouvre aucune transaction.
 */
final readonly class BlogPostSlugAssigner
{
    /**
     * @param  list<string>  $reservedWords  segments d'adresse du blog, interdits comme slug d'article
     */
    public function __construct(
        private BlogSlugGenerator $generator,
        private BlogPostSlugRepository $slugs,
        private array $reservedWords,
    ) {}

    /**
     * Slug a enregistrer pour cette langue.
     *
     * - Slug saisi : normalise, puis refuse (exception typee) s'il est reserve ou deja pris. Jamais de
     *   suffixe silencieux sur ce que la redactrice a choisi.
     * - Slug laisse vide : genere depuis le titre, avec un suffixe -2, -3... s'il le faut.
     * - Ni slug ni titre exploitable : null (la langue n'est pas encore redigee).
     *
     * @throws BlogSlugException
     */
    public function resolve(?int $postId, string $locale, ?string $typedSlug, ?string $title): ?string
    {
        $typed = $this->generator->fromText((string) $typedSlug);

        if ($typed !== '') {
            if ($this->isReserved($typed)) {
                throw BlogSlugException::reserved($locale, $typed);
            }

            if ($this->slugs->isTaken($locale, $typed, $postId)) {
                throw BlogSlugException::taken($locale, $typed);
            }

            return $typed;
        }

        $base = $this->generator->fromText((string) $title);

        if ($base === '') {
            return null;
        }

        return $this->generator->firstAvailable(
            $base,
            fn (string $candidate): bool => $this->isReserved($candidate) || $this->slugs->isTaken($locale, $candidate, $postId),
        );
    }

    /**
     * Met l'historique a jour apres un changement de slug. L'ancien slug n'est garde (redirection 301)
     * que si l'article a deja ete publie : avant, personne n'a pu voir son adresse. Le slug attribue
     * sort de l'historique s'il y figurait pour ce meme article ou pour un article supprime.
     */
    public function record(BlogPost $post, string $locale, ?string $previousSlug, ?string $newSlug): void
    {
        if ($previousSlug === $newSlug) {
            return;
        }

        if ($newSlug !== null) {
            BlogPostRetiredSlug::query()
                ->where('locale', $locale)
                ->where('slug', $newSlug)
                ->where(fn ($query) => $query->whereNull('blog_post_id')->orWhere('blog_post_id', $post->id))
                ->delete();
        }

        if ($previousSlug !== null && $post->hasBeenPublished()) {
            BlogPostRetiredSlug::query()->updateOrCreate(
                ['locale' => $locale, 'slug' => $previousSlug],
                ['blog_post_id' => $post->id],
            );
        }
    }

    /**
     * Avant la suppression d'un article deja publie : ses slugs vivants deviennent des lignes sans
     * article (reponse 410). Ses anciens slugs perdent leur article par la cle etrangere. Les
     * traductions de l'article doivent etre chargees.
     */
    public function entomb(BlogPost $post): void
    {
        if (! $post->hasBeenPublished()) {
            return;
        }

        foreach ($post->translations as $translation) {
            if (filled($translation->slug)) {
                BlogPostRetiredSlug::query()->updateOrCreate(
                    ['locale' => $translation->locale, 'slug' => $translation->slug],
                    ['blog_post_id' => null],
                );
            }
        }
    }

    private function isReserved(string $slug): bool
    {
        return in_array($slug, $this->reservedWords, true);
    }
}
