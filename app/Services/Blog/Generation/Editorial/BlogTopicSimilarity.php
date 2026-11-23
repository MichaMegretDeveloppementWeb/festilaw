<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Enums\Blog\Generation\BlogTopicKind;
use Illuminate\Support\Str;

/**
 * Second regard, en code, sur un sujet propose par le modele : un titre fait des memes mots qu'un article
 * ou qu'un sujet deja propose est un doublon, meme si le modele ne l'a pas vu (cahier des charges, § 11).
 * Les titres se comparent en mots significatifs (sans accents, sans casse, sans mots vides), par la part de
 * mots communs. Le controle est volontairement simple : il ecarte les reprises evidentes, le modele juge les
 * sujets voisins avec la liste complete sous les yeux.
 *
 * Un article d'actualite peut reprendre un sujet deja traite pour annoncer ce qui change (decision de Micha du
 * 19/11) : son titre reprend les mots de l'ancien et y ajoute le changement. Pour lui, la part de mots communs
 * se rapporte au plus long des deux titres, et deux titres d'actualite presque identiques restent un doublon.
 */
final class BlogTopicSimilarity
{
    /** Part de mots communs a partir de laquelle deux titres parlent de la meme chose. */
    private const THRESHOLD = 0.8;

    /** Mots qu'un titre de fond doit ajouter a un titre connu pour en etre un angle precis, dont un precis. */
    private const ANGLE_WORDS = 2;

    /**
     * Mots qui, ajoutes a un titre connu, n'en font pas un angle precis : ils annoncent une mise a jour ou un
     * format, pas un autre sujet (racines, comme `words()` les rend). Les nombres non plus.
     */
    private const GENERIC_WORDS = [
        'chang', 'change', 'new', 'updat', 'update', 'rul', 'rule', 'requirement', 'explain', 'complete', 'full', 'basic',
        'step', 'tip', 'tips', 'check', 'checklist', 'list', 'overview', 'simple', 'easy', 'quick', 'essential',
        'practical', 'everything', 'detail', 'nouveau', 'nouvell', 'nouvelle', 'regl', 'regle', 'exigenc', 'exigence',
        'nueva', 'nuevo', 'regla', 'requisito',
    ];

    /** Mots qui ne disent rien du sujet, dans les trois langues du blog. */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'to', 'for', 'of', 'in', 'on', 'and', 'or', 'your', 'you', 'what', 'how', 'why', 'with',
        'from', 'is', 'are', 'do', 'does', 'it', 'its', 'about', 'guide', 'need', 'know',
        'le', 'la', 'les', 'l', 'un', 'une', 'des', 'de', 'du', 'd', 'et', 'ou', 'pour', 'en', 'sur', 'votre', 'vos',
        'ce', 'qu', 'que', 'quoi', 'comment',
        'el', 'los', 'las', 'una', 'del', 'y', 'o', 'para', 'su', 'sus', 'que', 'como',
    ];

    /**
     * Le titre connu dont la proposition reprend le sujet, ou null.
     *
     * @param  list<string>  $knownTitles
     */
    public function duplicateOf(string $candidate, array $knownTitles, BlogTopicKind $kind = BlogTopicKind::Evergreen): ?string
    {
        $words = $this->words($candidate);

        if ($words === []) {
            return null;
        }

        foreach ($knownTitles as $title) {
            $other = $this->words($title);

            if ($other !== [] && $this->overlap($words, $other, $kind) >= self::THRESHOLD && ! $this->addsAnAngle($words, $other, $kind)) {
                return $title;
            }
        }

        return null;
    }

    /**
     * Un titre de fond qui reprend un titre connu en y ajoutant au moins deux mots, dont un precis (une
     * plateforme, un type de produit, un pays), traite un angle precis, et la consigne du sujet en demande
     * (controle du 20/11) : « GPSR label requirements for Etsy jewellery sellers » n'est pas « GPSR labels for
     * Etsy sellers ». Une reformulation qui n'ajoute qu'un mot (« Adding ... »), ou que des mots generiques et une
     * date (« what changes in 2027 », l'annonce d'un changement est un article d'actualite, decision de Micha du
     * 19/11), reste un doublon.
     *
     * @param  list<string>  $candidate
     * @param  list<string>  $known
     */
    private function addsAnAngle(array $candidate, array $known, BlogTopicKind $kind): bool
    {
        if ($kind === BlogTopicKind::News) {
            return false;
        }

        $added = array_diff($candidate, $known);
        $specific = array_filter($added, static fn (string $word): bool => ! ctype_digit($word) && ! in_array($word, self::GENERIC_WORDS, true));

        return count($added) >= self::ANGLE_WORDS && $specific !== [];
    }

    /** @return list<string> */
    private function words(string $title): array
    {
        $normalized = Str::lower(Str::ascii($title));
        preg_match_all('/[a-z0-9]+/', $normalized, $matches);

        return array_values(array_unique(array_map($this->stem(...), array_diff($matches[0], self::STOP_WORDS))));
    }

    /** Une racine grossiere : sans pluriel ni terminaison verbale courante (labels, adding, labelled). */
    private function stem(string $word): string
    {
        if (strlen($word) <= 4) {
            return $word;
        }

        return (string) preg_replace('/(?:ing|ed|es|s|x)$/', '', $word);
    }

    /**
     * Mots communs rapportes au plus petit des deux titres : un titre court entierement repris par un plus
     * long compte comme un doublon. Pour un article d'actualite, au plus long : le changement qu'il ajoute a
     * un sujet traite en fait un autre sujet.
     *
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function overlap(array $first, array $second, BlogTopicKind $kind): float
    {
        $sizes = [count($first), count($second)];

        return count(array_intersect($first, $second)) / ($kind === BlogTopicKind::News ? max($sizes) : min($sizes));
    }
}
