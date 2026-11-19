<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

/**
 * Verifie qu'une recriture (passe de style, traduction) n'a touche a aucun fait : les memes nombres, les
 * memes liens, les memes jetons de liens internes et les memes emplacements d'images, autant de fois
 * chacun. Les nombres se comparent chiffres seuls, pour qu'une traduction puisse ecrire 1 200 ou 1,5 la
 * ou l'anglais ecrit 1,200 ou 1.5. Rend la liste des differences, vide si la recriture est fidele.
 */
final class BlogFactGuard
{
    /**
     * @return list<string> chaque difference, en anglais, pour le journal technique
     */
    public function differences(string $before, string $after): array
    {
        $differences = [];

        foreach (['numbers' => $this->numbers(...), 'links' => $this->links(...), 'link tokens' => EditorialMarkdown::linkTokens(...), 'image placeholders' => $this->placeholders(...), 'image text ends' => $this->ends(...)] as $kind => $extract) {
            $missing = $this->without($extract($before), $extract($after));
            $added = $this->without($extract($after), $extract($before));

            if ($missing !== [] || $added !== []) {
                $differences[] = sprintf('%s changed: missing [%s], added [%s]', $kind, implode(', ', $missing), implode(', ', $added));
            }
        }

        return $differences;
    }

    /**
     * Les nombres d'un texte, ramenes a leurs seuls chiffres (1 700, 1,700 et 1.700 se valent).
     *
     * @return list<string>
     */
    public function numbers(string $text): array
    {
        // Liens et reperes retires d'abord : leurs chiffres sont compares a part.
        $text = (string) preg_replace(['/\]\([^)]*\)/', EditorialMarkdown::IMAGE_MARKER], [']', ''], $text);
        preg_match_all('/\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})+(?:[.,]\d+)?|\d+(?:[.,]\d+)*/u', $text, $matches);

        return array_map(static fn (string $number): string => (string) preg_replace('/\D/', '', $number), $matches[0]);
    }

    /** @return list<string> les adresses des liens, hors jetons internes */
    private function links(string $text): array
    {
        preg_match_all('/\]\(([^)\s]+)\)/', $text, $matches);

        return array_values(array_filter($matches[1], static fn (string $url): bool => ! str_starts_with($url, 'festilaw:')));
    }

    /** @return list<string> */
    private function placeholders(string $text): array
    {
        return array_map(strval(...), EditorialMarkdown::imagePlaceholders($text));
    }

    /**
     * Une fin de texte a cote d'une image perdue ferait passer tout le texte suivant a cote d'elle, ou l'en
     * sortirait : elle se compte comme un emplacement.
     *
     * @return list<string>
     */
    private function ends(string $text): array
    {
        return array_map(strval(...), EditorialMarkdown::imageEnds($text));
    }

    /**
     * Ce qui est dans la premiere liste et pas dans la seconde, en tenant compte des repetitions.
     *
     * @param  list<string>  $items
     * @param  list<string>  $others
     * @return list<string>
     */
    private function without(array $items, array $others): array
    {
        $left = [];

        foreach ($items as $item) {
            $index = array_search($item, $others, true);

            if ($index === false) {
                $left[] = $item;
            } else {
                unset($others[$index]);
            }
        }

        return $left;
    }
}
