<?php

declare(strict_types=1);

namespace App\Services\Blog\Slug;

use Closure;
use Illuminate\Support\Str;
use LogicException;

/**
 * Fabrique un slug a partir d'un texte libre (titre, ou slug saisi a la main). Calcul pur : aucune
 * lecture en base. Regles : signes d'ouverture espagnols retires, accents translitteres, minuscules,
 * tout ce qui n'est ni lettre ni chiffre devient un tiret, coupe entre deux mots au-dela de la
 * longueur maximale.
 */
final readonly class BlogSlugGenerator
{
    private const MAX_SUFFIX_ATTEMPTS = 500;

    public function __construct(private int $maxLength = 75) {}

    /** Chaine vide si le texte ne contient rien d'exploitable. */
    public function fromText(string $text): string
    {
        $text = str_replace(['¿', '¡'], '', $text);
        $ascii = Str::lower(Str::ascii($text));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');

        return $this->truncate($slug, $this->maxLength);
    }

    /**
     * Premier slug libre parmi `base`, `base-2`, `base-3`... Le suffixe ne fait jamais depasser la
     * longueur maximale : c'est la base qui est raccourcie.
     *
     * @param  Closure(string): bool  $isTaken
     */
    public function firstAvailable(string $base, Closure $isTaken): string
    {
        if (! $isTaken($base)) {
            return $base;
        }

        for ($number = 2; $number < self::MAX_SUFFIX_ATTEMPTS; $number++) {
            $suffix = '-'.$number;
            $candidate = $this->truncate($base, $this->maxLength - strlen($suffix)).$suffix;

            if (! $isTaken($candidate)) {
                return $candidate;
            }
        }

        throw new LogicException("No available slug found for base [{$base}] after ".self::MAX_SUFFIX_ATTEMPTS.' attempts.');
    }

    /** Coupe sur un tiret (entre deux mots) quand le slug depasse la limite. */
    private function truncate(string $slug, int $limit): string
    {
        if (strlen($slug) <= $limit) {
            return $slug;
        }

        $cut = substr($slug, 0, $limit);

        // Le caractere suivant n'est pas un tiret : on a coupe au milieu d'un mot, on le retire.
        if ($slug[$limit] !== '-') {
            $lastHyphen = strrpos($cut, '-');

            if ($lastHyphen !== false && $lastHyphen > 0) {
                $cut = substr($cut, 0, $lastHyphen);
            }
        }

        return rtrim($cut, '-');
    }
}
