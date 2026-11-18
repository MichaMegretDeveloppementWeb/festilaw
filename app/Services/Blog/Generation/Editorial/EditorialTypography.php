<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

use App\Data\Blog\Generation\EditorialArticle;

/**
 * La typographie de la langue, posee en code sur la version francaise ou espagnole d'un article, sans appel
 * a l'IA. Les modeles ecrivent la ponctuation anglaise (guillemets droits, aucune espace avant « : ; ! ? »),
 * l'un des signes les plus visibles d'une traduction (relecture du brouillon 99 par Micha, 18/11).
 *
 * Francais (usage de France, writing-voice-fr.md) : espace insecable avant « : » et « % », espace fine
 * insecable avant « ; ! ? », espaces insecables dans les guillemets « », espace insecable entre les
 * tranches de chiffres. Espagnol (Diccionario panhispanico de dudas, « comillas ») : comillas angulares
 * collees au texte. L'adresse d'un lien, un jeton festilaw:… et un emplacement d'image ne sont jamais
 * touches. Appliquee deux fois, elle ne change plus rien.
 */
final class EditorialTypography
{
    private const NBSP = "\u{00A0}";

    private const NARROW_NBSP = "\u{202F}";

    /** Ce qui ne se retouche jamais : cible d'un lien Markdown, emplacement d'image, code, adresse, jeton interne. */
    private const PROTECTED = '/(\]\([^)]*\)|\[\[image:\d+\]\]|`[^`]*`|<https?:\/\/[^>\s]+>|https?:\/\/[^\s)\]]+|festilaw:[^\s)\]]+)/u';

    public function article(EditorialArticle $article, string $locale): EditorialArticle
    {
        return EditorialArticle::fromArray(array_map(fn (string $text): string => $this->apply($text, $locale), $article->toArray()));
    }

    public function apply(string $text, string $locale): string
    {
        $parts = preg_split(self::PROTECTED, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $text;
        }

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = match ($locale) {
                    'fr' => $this->french($part),
                    'es' => $this->spanish($part),
                    default => $part,
                };
            }
        }

        return implode('', $parts);
    }

    private function french(string $text): string
    {
        $text = (string) preg_replace('/"([^"\n]+)"/u', '«$1»', $text);
        $text = (string) preg_replace('/«[ \x{00A0}\x{202F}]*/u', '«'.self::NBSP, $text);
        $text = (string) preg_replace('/[ \x{00A0}\x{202F}]*»/u', self::NBSP.'»', $text);
        $text = (string) preg_replace('/(?<=[^\s;!?])[ \x{00A0}\x{202F}]?([;!?])/u', self::NARROW_NBSP.'$1', $text);
        $text = (string) preg_replace('/(?<=\S)[ \x{00A0}\x{202F}]?:(?=\s|$)/u', self::NBSP.':', $text);
        $text = (string) preg_replace('/(\d)[ \x{00A0}\x{202F}]?%/u', '$1'.self::NBSP.'%', $text);

        return (string) preg_replace_callback(
            '/(?<![\d,.])\d{1,3}(?:[ \x{202F}]\d{3})+(?!\d)/u',
            static fn (array $match): string => (string) preg_replace('/[ \x{202F}]/u', self::NBSP, $match[0]),
            $text,
        );
    }

    private function spanish(string $text): string
    {
        $text = (string) preg_replace('/"([^"\n]+)"/u', '«$1»', $text);
        $text = (string) preg_replace('/«[ \x{00A0}\x{202F}]+/u', '«', $text);

        return (string) preg_replace('/[ \x{00A0}\x{202F}]+»/u', '»', $text);
    }
}
