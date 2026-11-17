<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial;

/**
 * Les reperes que les agents ecrivent dans le Markdown d'un article, a la place de ce qui depend de la
 * langue ou n'existe pas encore :
 * - un lien interne s'ecrit `[texte](festilaw:post/12)` ou `[texte](festilaw:page/pricing)`, et devient
 *   l'adresse de la page dans la langue de chaque version a l'assemblage ;
 * - l'emplacement d'une image du texte s'ecrit `[[image:1]]`, seul sur sa ligne, et devient l'image a
 *   l'assemblage.
 * Une traduction doit rendre exactement les memes reperes que l'anglais.
 */
final class EditorialMarkdown
{
    /** Un lien interne en jeton : le type (post, page) et sa cle. */
    public const LINK_TOKEN = '~\]\((festilaw:(post|page)/([a-z0-9-]+))\)~';

    /** L'emplacement d'une image du texte, numerote a partir de 1. */
    public const IMAGE_PLACEHOLDER = '~\[\[image:(\d+)\]\]~';

    /**
     * Les jetons de liens, dans l'ordre du texte (un meme jeton peut revenir).
     *
     * @return list<string>
     */
    public static function linkTokens(string $markdown): array
    {
        preg_match_all(self::LINK_TOKEN, $markdown, $matches);

        return $matches[1];
    }

    /**
     * Les numeros des emplacements d'images, dans l'ordre du texte.
     *
     * @return list<int>
     */
    public static function imagePlaceholders(string $markdown): array
    {
        preg_match_all(self::IMAGE_PLACEHOLDER, $markdown, $matches);

        return array_map(intval(...), $matches[1]);
    }
}
