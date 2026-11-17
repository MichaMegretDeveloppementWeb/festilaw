<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use Illuminate\Support\Str;

/**
 * Second regard, en code, sur un sujet propose par le modele : un titre fait des memes mots qu'un article
 * ou qu'un sujet deja propose est un doublon, meme si le modele ne l'a pas vu (cahier des charges, § 11).
 * Les titres se comparent en mots significatifs (sans accents, sans casse, sans mots vides), par la part de
 * mots communs. Le controle est volontairement simple : il ecarte les reprises evidentes, le modele juge les
 * sujets voisins avec la liste complete sous les yeux.
 */
final class BlogTopicSimilarity
{
    /** Part de mots communs a partir de laquelle deux titres parlent de la meme chose. */
    private const THRESHOLD = 0.8;

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
    public function duplicateOf(string $candidate, array $knownTitles): ?string
    {
        $words = $this->words($candidate);

        if ($words === []) {
            return null;
        }

        foreach ($knownTitles as $title) {
            $other = $this->words($title);

            if ($other !== [] && $this->overlap($words, $other) >= self::THRESHOLD) {
                return $title;
            }
        }

        return null;
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
     * long compte comme un doublon.
     *
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function overlap(array $first, array $second): float
    {
        return count(array_intersect($first, $second)) / min(count($first), count($second));
    }
}
