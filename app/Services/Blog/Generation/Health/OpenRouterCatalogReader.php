<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Health;

use App\Data\Blog\Generation\Health\OpenRouterKeyStatus;
use App\Data\Blog\Generation\Health\OpenRouterModelOffer;
use App\Exceptions\Blog\BlogHealthCheckException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Ce qu'OpenRouter dit de la cle du site et de ses modeles, par ses adresses gratuites, sans jamais appeler un
 * modele (documentation relue le 20/11) : `/key` (cle acceptee, limite restante), `/models` (modeles listes,
 * date de retrait ; les modeles d'images seulement avec `output_modalities=image`) et
 * `/models/{id}/endpoints` (fournisseurs d'un modele, leurs prix et ce qu'ils acceptent).
 */
final readonly class OpenRouterCatalogReader
{
    private const TIMEOUT_SECONDS = 20;

    /** Prix d'OpenRouter en dollars par jeton, ramenes en cents par million de jetons comme le catalogue. */
    private const CENTS_PER_MILLION = 100_000_000;

    public function __construct(
        private ?string $apiKey,
        private string $baseUrl,
    ) {}

    /** @throws BlogHealthCheckException */
    public function keyStatus(): OpenRouterKeyStatus
    {
        $response = $this->get('/key', authenticated: true);

        if (in_array($response->status(), [401, 403], true)) {
            throw BlogHealthCheckException::keyRejected($response->status());
        }

        $this->ensureSuccessful($response, '/key');
        $data = (array) $response->json('data');

        return new OpenRouterKeyStatus(
            is_numeric($data['limit'] ?? null) ? (float) $data['limit'] : null,
            is_numeric($data['limit_remaining'] ?? null) ? (float) $data['limit_remaining'] : null,
        );
    }

    /**
     * @return array<string, ?string> date de retrait annoncee (AAAA-MM-JJ), ou nulle, par modele liste
     *
     * @throws BlogHealthCheckException
     */
    public function listedModels(bool $imageOutput): array
    {
        $path = $imageOutput ? '/models?output_modalities=image' : '/models';
        $response = $this->get($path);
        $this->ensureSuccessful($response, $path);

        $listed = [];

        foreach ((array) $response->json('data') as $model) {
            if (is_array($model) && is_string($model['id'] ?? null)) {
                $listed[$model['id']] = is_string($model['expiration_date'] ?? null) ? $model['expiration_date'] : null;
            }
        }

        return $listed;
    }

    /**
     * L'offre d'un modele, ou nulle s'il n'existe plus chez OpenRouter.
     *
     * @throws BlogHealthCheckException
     */
    public function offer(string $modelId): ?OpenRouterModelOffer
    {
        $path = '/models/'.$modelId.'/endpoints';
        $response = $this->get($path);

        if ($response->status() === 404) {
            return null;
        }

        $this->ensureSuccessful($response, $path);
        $data = (array) $response->json('data');
        $cheapest = null;

        foreach ((array) ($data['endpoints'] ?? []) as $endpoint) {
            if (! is_array($endpoint) || ! $this->acceptsWhatTheSiteSends($endpoint)) {
                continue;
            }

            $input = $this->cents($endpoint['pricing']['prompt'] ?? null);
            $output = $this->cents($endpoint['pricing']['completion'] ?? null);

            if ($input !== null && $output !== null && ($cheapest === null || $input + $output < $cheapest[0] + $cheapest[1])) {
                $cheapest = [$input, $output];
            }
        }

        return new OpenRouterModelOffer(
            $modelId,
            $cheapest !== null,
            $cheapest[0] ?? null,
            $cheapest[1] ?? null,
            in_array('image', (array) ($data['architecture']['input_modalities'] ?? []), true),
        );
    }

    /**
     * Le site demande la sortie structuree et la reflexion : OpenRouter ne route que vers ceux qui les acceptent.
     * Il ne demande aucun palier de service : un fournisseur « flex » (moitie prix) ou « priority » (double prix)
     * ne sert ses appels que sur demande, son prix ne compte donc pas (releve du 23/11 : GPT-6 Luna et GPT-6.1
     * Terra ont un palier flex a moitie prix chez OpenAI, nos appels passent par Amazon Bedrock et Azure au prix
     * normal).
     */
    private function acceptsWhatTheSiteSends(array $endpoint): bool
    {
        $parameters = (array) ($endpoint['supported_parameters'] ?? []);
        $tier = $endpoint['service_tier'] ?? null;

        return in_array('structured_outputs', $parameters, true) && in_array('reasoning', $parameters, true)
            && in_array($tier, [null, '', 'default', 'standard'], true);
    }

    private function cents(mixed $dollarsPerToken): ?float
    {
        return is_numeric($dollarsPerToken) && (float) $dollarsPerToken >= 0
            ? round((float) $dollarsPerToken * self::CENTS_PER_MILLION, 2)
            : null;
    }

    /** @throws BlogHealthCheckException */
    private function get(string $path, bool $authenticated = false): Response
    {
        $request = Http::timeout(self::TIMEOUT_SECONDS)->connectTimeout(5)->acceptJson();

        if ($authenticated) {
            $request = $request->withToken((string) $this->apiKey);
        }

        try {
            return $request->get(rtrim($this->baseUrl, '/').$path);
        } catch (ConnectionException $e) {
            throw BlogHealthCheckException::unreachable('OpenRouter', "no answer from {$path}", $e);
        }
    }

    /** @throws BlogHealthCheckException */
    private function ensureSuccessful(Response $response, string $path): void
    {
        if (! $response->successful()) {
            throw BlogHealthCheckException::unreachable('OpenRouter', "{$path} answered HTTP {$response->status()}");
        }
    }
}
