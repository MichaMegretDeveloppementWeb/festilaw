<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

/**
 * Compte les signes mesurables d'un texte genere (annexe A.12, point 3, tache 6.19) et releve chaque phrase
 * qui en porte un. Signes communs a toutes les langues : tirets employes comme liaison, points-virgules,
 * deux-points par paragraphe et dans les intertitres, regularite de la longueur des phrases, paragraphes qui
 * finissent sur une phrase tres courte. Signes propres a la langue, lus dans son fichier (signals-xx.md) :
 * des expressions, et des tournures decrites par une expression reguliere.
 *
 * Un signe isole ne trahit rien, c'est leur accumulation qui se voit. La relecture du style s'en sert depuis
 * le 17/11 : au-dela d'un seuil, elle renvoie les phrases relevees au modele, une fois.
 */
final class BlogWritingSignalsInspector
{
    /** Une phrase de cette longueur ou moins, en fin de paragraphe, compte comme une chute. */
    private const SHORT_ENDING_WORDS = 5;

    /** Au-dela, une phrase en contient presque toujours deux (Plain English Campaign : 15 a 20 mots en moyenne). */
    private const LONG_SENTENCE_WORDS = 35;

    /** Tant de phrases de suite ouvertes par le meme mot font un signe (humanizer, motif 7). */
    private const REPEATED_OPENINGS = 3;

    /** Signes de ponctuation, communs a toutes les langues. */
    private const PUNCTUATION_SIGNS = [
        'dash used to join clauses' => '/[\x{2014}\x{2013}]|(?<=\S) - (?=\S)/u',
        'semicolon joining clauses' => '/;/u',
    ];

    /**
     * @param  list<string>  $signs  les signes de la langue, une ligne chacun : une expression (mot entier, sans
     *                               casse), ou « ~ expression reguliere => nom » pour une tournure
     * @return array{words: int, sentences: int, dashes: int, semicolons: int, colon_paragraphs: int, colons_in_headings: int, phrases: array<string, int>, patterns: array<string, int>, flagged: list<array{signs: list<string>, sentence: string}>, sentence_length_mean: float, sentence_length_variation: float, short_endings: int}
     */
    public function inspect(string $markdown, array $signs): array
    {
        [$phrases, $patterns] = $this->split($signs);

        $blocks = array_values(array_filter(array_map(trim(...), preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $markdown)) ?: [])));
        $headings = array_values(array_filter($blocks, static fn (string $block): bool => str_starts_with($block, '#')));
        $prose = array_values(array_filter($blocks, static fn (string $block): bool => ! str_starts_with($block, '#')
            && preg_match(EditorialMarkdown::IMAGE_PLACEHOLDER, $block) !== 1));
        $paragraphs = array_values(array_filter($prose, static fn (string $block): bool => preg_match('/^\s*(?:[-*+]|\d+[.)])\s/', $block) !== 1));

        $plainProse = $this->plain(implode("\n\n", $prose));
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

        $flagged = [];
        $patternCounts = [];
        $openings = [];

        foreach ($prose as $block) {
            foreach (preg_split('/\n/', $this->plain($block)) ?: [] as $line) {
                foreach ($this->sentences($line) as $sentence) {
                    $found = $this->signsOf($sentence, $phrases, $patterns);

                    if ($this->wordCount($sentence) > self::LONG_SENTENCE_WORDS) {
                        $found[] = 'sentence over '.self::LONG_SENTENCE_WORDS.' words';
                    }

                    $openings[] = mb_strtolower((string) preg_replace('/^\W*(\w+).*$/us', '$1', $sentence));
                    $lastOpenings = array_slice($openings, -self::REPEATED_OPENINGS);

                    if (count($lastOpenings) === self::REPEATED_OPENINGS && count(array_unique($lastOpenings)) === 1) {
                        $found[] = 'same opening word as the two sentences before';
                    }

                    foreach (array_intersect($found, array_unique(array_column($patterns, 'name'))) as $name) {
                        $patternCounts[$name] = ($patternCounts[$name] ?? 0) + 1;
                    }

                    if ($found !== []) {
                        $flagged[] = ['signs' => $found, 'sentence' => $sentence];
                    }
                }
            }
        }

        $headingsWithColon = array_values(array_filter($headings, fn (string $heading): bool => $this->colons($this->plain($heading)) > 0));

        foreach ($headingsWithColon as $heading) {
            $flagged[] = ['signs' => ['colon in a heading'], 'sentence' => $this->plain($heading)];
        }

        return [
            'words' => $this->wordCount($plainProse),
            'sentences' => count($sentenceLengths),
            'dashes' => preg_match_all(self::PUNCTUATION_SIGNS['dash used to join clauses'], $plainProse),
            'semicolons' => substr_count($plainProse, ';'),
            'colon_paragraphs' => $colonParagraphs,
            'colons_in_headings' => count($headingsWithColon),
            'phrases' => $this->phrases($plainProse, $phrases),
            'patterns' => $patternCounts,
            'flagged' => $flagged,
            'sentence_length_mean' => $this->mean($sentenceLengths),
            'sentence_length_variation' => $this->variation($sentenceLengths),
            'short_endings' => $shortEndings,
        ];
    }

    /**
     * Les expressions d'un cote, les tournures de l'autre (plusieurs tournures peuvent porter le meme nom).
     *
     * @param  list<string>  $signs
     * @return array{0: list<string>, 1: list<array{name: string, pattern: string}>}
     */
    public function split(array $signs): array
    {
        $phrases = [];
        $patterns = [];

        foreach ($signs as $sign) {
            if (preg_match('/^~\s*(.+?)\s+=>\s+(.+)$/u', $sign, $match) === 1) {
                $patterns[] = ['name' => trim($match[2]), 'pattern' => '{'.$match[1].'}iu'];
            } elseif (trim($sign) !== '') {
                $phrases[] = trim($sign);
            }
        }

        return [$phrases, $patterns];
    }

    /**
     * Les signes que porte une phrase : ponctuation, expressions, tournures.
     *
     * @param  list<string>  $phrases
     * @param  list<array{name: string, pattern: string}>  $patterns
     * @return list<string>
     */
    private function signsOf(string $sentence, array $phrases, array $patterns): array
    {
        $found = [];
        $lower = mb_strtolower($sentence);

        foreach (self::PUNCTUATION_SIGNS as $name => $pattern) {
            if (preg_match($pattern, $sentence) === 1) {
                $found[] = $name;
            }
        }

        foreach ($patterns as $pattern) {
            if (! in_array($pattern['name'], $found, true) && preg_match($pattern['pattern'], $sentence) === 1) {
                $found[] = $pattern['name'];
            }
        }

        foreach ($phrases as $phrase) {
            if (preg_match($this->wholeWords($phrase), $lower) === 1) {
                $found[] = 'word "'.$phrase.'"';
            }
        }

        return $found;
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

    private function wholeWords(string $phrase): string
    {
        return '/(?<![\p{L}])'.preg_quote(mb_strtolower($phrase), '/').'(?![\p{L}])/u';
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
            $count = preg_match_all($this->wholeWords($phrase), $haystack);

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
