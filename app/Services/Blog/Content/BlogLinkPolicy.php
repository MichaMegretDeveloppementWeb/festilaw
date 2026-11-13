<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

/**
 * Liens acceptes dans le blog (contenu, sources, credits d'image) : http, https, mailto, ou un chemin
 * interne commencant par "/". Tout le reste est refuse (javascript:, data:, adresse sans protocole,
 * "//hote" qui sortirait du site). Calcul pur.
 */
final readonly class BlogLinkPolicy
{
    private const MAX_LENGTH = 2000;

    /**
     * @param  string  $siteHost  hote du site (sans protocole), pour reconnaitre un lien externe
     */
    public function __construct(private string $siteHost = '') {}

    public function isAllowed(string $href): bool
    {
        if ($href === '' || strlen($href) > self::MAX_LENGTH) {
            return false;
        }

        // Ni espace ni caractere de controle : ils servent a maquiller un protocole ("java\tscript:").
        if (preg_match('/[\x00-\x20\x7F]/', $href) === 1) {
            return false;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return filter_var($href, FILTER_VALIDATE_URL) !== false;
        }

        if (preg_match('/^mailto:[^@<>"\']+@[^@<>"\'?]+(\?[^<>"\']*)?$/i', $href) === 1) {
            return true;
        }

        return str_starts_with($href, '/')
            && ! str_starts_with($href, '//')
            && ! str_starts_with($href, '/\\');
    }

    /** Lien vers un autre site : il s'ouvrira dans un nouvel onglet. */
    public function isExternal(string $href): bool
    {
        if (preg_match('#^https?://#i', $href) !== 1) {
            return false;
        }

        $host = strtolower((string) parse_url($href, PHP_URL_HOST));

        return $this->withoutWww($host) !== $this->withoutWww(strtolower($this->siteHost));
    }

    private function withoutWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
