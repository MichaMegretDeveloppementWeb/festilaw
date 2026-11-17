<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

/**
 * Compte quelques signes mesurables d'un texte genere (annexe A.12, point 3, tache 6.19) : tirets employes
 * comme liaison, points-virgules, deux-points par paragraphe et dans les intertitres, expressions de la
 * liste de la langue, regularite de la longueur des phrases, paragraphes qui finissent sur une phrase tres
 * courte. Le resultat va au journal technique de l'etape et sert au banc d'essai ; il ne bloque rien. Un
 * signe isole ne trahit rien, c'est leur accumulation qui se voit.
 */
final class BlogWritingSignalsInspector
{
    /** Une phrase de cette longueur ou moins, en fin de paragraphe, compte comme une chute. */
    private const SHORT_ENDING_WORDS = 5;

    /**
     * @param  list<string>  $phrases  les expressions a reperer dans cette langue (sans casse)
     * @return array{words: int, sentences: int, dashes: int, semicolons: int, colon_paragraphs: int, colons_in_headings: int, phrases: array<string, int>, sentence_length_mean: float, sentence_length_variation: float, short_endings: int}
     */
    public function inspect(string $markdown, array $phrases): array
    {
        $blocks = array_values(array_filter(array_map(trim(...), preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $markdown)) ?: [])));
        $headings = array_filter($blocks, static fn (string $block): bool => str_starts_with($block, '#'));
        $paragraphs = array_values(array_filter($blocks, static fn (string $block): bool => ! str_starts_with($block, '#')
            && preg_match('/^\s*(?:[-*+]|\d+[.)])\s/', $block) !== 1
            && preg_match(EditorialMarkdown::IMAGE_PLACEHOLDER, $block) !== 1));

        $prose = $this->plain(implode("\n\n", array_filter($blocks, static fn (string $block): bool => ! str_starts_with($block, '#'))));
        $sentenceLengths = [];
        $shortEndings = 0;
        $colonParagraphs = 0;

        foreach ($paragraphs as $paragraph) {
            $text = $this->plain($paragraph);
            $lengths = array_map($this->wordCount(...), $this->sentences($text));
            $sentenceLengths = [...$sentenceLengths, ...$lengths];

            if (count($lengths) >= 2 && end($lengths) <= self::SHORT_ENDING_WORDS) {
                $shortEndings++;
            }

            if ($this->colons($text) >= 2) {
                $colonParagraphs++;
            }
        }

        return [
            'words' => $this->wordCount($prose),
            'sentences' => count($sentenceLengths),
            'dashes' => preg_match_all('/[\x{2014}\x{2013}]|(?<=\S) - (?=\S)/u', $prose),
            'semicolons' => substr_count($prose, ';'),
            'colon_paragraphs' => $colonParagraphs,
            'colons_in_headings' => count(array_filter($headings, fn (string $heading): bool => $this->colons($this->plain($heading)) > 0)),
            'phrases' => $this->phrases($prose, $phrases),
            'sentence_length_mean' => $this->mean($sentenceLengths),
            'sentence_length_variation' => $this->variation($sentenceLengths),
            'short_endings' => $shortEndings,
        ];
    }

    /** Le texte sans balisage : liens reduits a leur texte, marques retirees. */
    private function plain(string $markdown): string
    {
        $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $markdown);
        $text = (string) preg_replace(EditorialMarkdown::IMAGE_PLACEHOLDER, '', $text);

        return trim((string) preg_replace(['/^#+\s*/m', '/^\s*(?:[-*+]|\d+[.)])\s+/m', '/[*_`]/'], '', $text));
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/(?<=[.!?…])\s+/u', $text) ?: [])));
    }

    private function wordCount(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);
    }

    /** Les deux-points d'un texte, sans ceux d'une heure (10:30). */
    private function colons(string $text): int
    {
        return preg_match_all('/(?<!\d):(?!\d)/', $text);
    }

    /**
     * @param  list<string>  $phrases
     * @return array<string, int>
     */
    private function phrases(string $text, array $phrases): array
    {
        $found = [];
        $haystack = mb_strtolower($text);

        foreach ($phrases as $phrase) {
            $count = preg_match_all('/(?<![\p{L}])'.preg_quote(mb_strtolower($phrase), '/').'(?![\p{L}])/u', $haystack);

            if ($count > 0) {
                $found[$phrase] = $count;
            }
        }

        return $found;
    }

    /** @param  list<int>  $values */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : round(array_sum($values) / count($values), 1);
    }

    /**
     * L'ecart type rapporte a la moyenne : plus il est bas, plus les phrases se ressemblent en longueur.
     *
     * @param  list<int>  $values
     */
    private function variation(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(static fn (int $value): float => ($value - $mean) ** 2, $values)) / count($values);

        return $mean > 0 ? round(sqrt($variance) / $mean, 2) : 0.0;
    }
}
