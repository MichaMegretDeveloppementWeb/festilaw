<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Sources;

use App\Contracts\Blog\Generation\HostResolver;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Teste les liens de source d'un article avant qu'ils n'y entrent (cahier des charges, § 11 et § 15). Les
 * adresses viennent d'un modele, qui a lu le web : elles ne sont pas sures. Seuls `http` et `https` sur leur
 * port habituel passent ; le nom est resolu, et toute adresse privee, locale ou reservee est refusee ; la
 * requete part vers l'adresse verifiee (pas de seconde resolution entre-temps) ; chaque redirection est
 * controlee de la meme facon, trois au plus ; le delai est court. Les liens d'un lot partent en parallele.
 *
 * Un lien est mort quand il ne se resout pas, ne repond pas, ou repond 404 ou 410. Un refus (401, 403, 429)
 * ou une erreur du serveur ne prouve pas qu'il est mort : beaucoup de sites officiels refusent les robots,
 * et le lien reste.
 */
final readonly class SourceLinkChecker
{
    private const DEAD_STATUSES = [404, 410];

    private const USER_AGENT = 'Mozilla/5.0 (compatible; LinkCheck/1.0)';

    public function __construct(
        private HostResolver $resolver,
        private int $timeoutSeconds = 8,
        private int $maxRedirects = 3,
    ) {}

    /**
     * @param  list<string>  $urls
     * @return array<string, bool> chaque adresse donnee, vraie si le lien est vivant
     */
    public function check(array $urls): array
    {
        $verdicts = [];
        /** @var array<string, string> $pending adresse donnee => adresse a demander a ce tour */
        $pending = [];

        foreach (array_unique($urls) as $url) {
            $pending[$url] = $url;
        }

        for ($round = 0; $round <= $this->maxRedirects && $pending !== []; $round++) {
            $targets = [];

            foreach ($pending as $original => $current) {
                $target = $this->target($current);

                if ($target === null) {
                    $verdicts[$original] = false;
                } else {
                    $targets[$original] = $target;
                }
            }

            $responses = $targets === [] ? [] : $this->fetch($targets);
            $pending = [];

            foreach ($targets as $original => $target) {
                $response = $responses[$original] ?? null;

                if (! $response instanceof Response) {
                    $verdicts[$original] = false;

                    continue;
                }

                if ($response->redirect() && filled($response->header('Location'))) {
                    $pending[$original] = (string) UriResolver::resolve(new Uri($target['url']), new Uri($response->header('Location')));

                    continue;
                }

                $verdicts[$original] = ! in_array($response->status(), self::DEAD_STATUSES, true);
            }
        }

        // Trop de redirections : le lien ne mene nulle part de stable.
        foreach (array_keys($pending) as $original) {
            $verdicts[$original] = false;
        }

        return $verdicts;
    }

    /**
     * L'adresse a demander et l'adresse IP verifiee de son hote, ou null si elle est refusee.
     *
     * @return array{url: string, host: string, port: int, ip: string}|null
     */
    private function target(string $url): ?array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $defaultPort = match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };

        if ($defaultPort === null || (isset($parts['port']) && $parts['port'] !== $defaultPort)) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolver->resolve($host);

        if ($addresses === [] || array_filter($addresses, fn (string $ip): bool => ! $this->isPublic($ip)) !== []) {
            return null;
        }

        return ['url' => $url, 'host' => $host, 'port' => $defaultPort, 'ip' => $addresses[0]];
    }

    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Une requete par adresse, en parallele, sans suivre les redirections, vers l'adresse IP verifiee.
     *
     * @param  array<string, array{url: string, host: string, port: int, ip: string}>  $targets
     * @return array<string, mixed>
     */
    private function fetch(array $targets): array
    {
        try {
            return Http::pool(fn (Pool $pool): array => array_map(
                fn (string $key): mixed => $pool->as($key)
                    ->withUserAgent(self::USER_AGENT)
                    ->withoutRedirecting()
                    ->timeout($this->timeoutSeconds)
                    ->connectTimeout(min(4, $this->timeoutSeconds))
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => [$this->pin($targets[$key])]]])
                    ->get($targets[$key]['url']),
                array_keys($targets),
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /** @param  array{url: string, host: string, port: int, ip: string}  $target */
    private function pin(array $target): string
    {
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];

        return $target['host'].':'.$target['port'].':'.$ip;
    }
}
