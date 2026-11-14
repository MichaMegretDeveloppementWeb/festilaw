<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Repositories\SettingRepository;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Le passage de relais entre workers. Le planificateur en lance un chaque minute ; chacun s'inscrit ici en
 * demarrant, et le precedent s'arrete quand il voit qu'il n'est plus le dernier inscrit. Il y a donc
 * toujours un worker a l'ecoute, sans trou entre deux. L'inscription vit dans la table settings (pas dans
 * le cache, que le deploiement vide).
 */
final readonly class BlogGenerationWorkerRegistry
{
    public const KEY = 'blog.ai.worker';

    public function __construct(private SettingRepository $settings) {}

    public function register(string $workerId): void
    {
        $this->settings->put(self::KEY, json_encode([
            'id' => $workerId,
            'started_at' => CarbonImmutable::now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    /** Faux des qu'un worker plus recent s'est inscrit : celui-ci peut s'arreter. */
    public function isCurrent(string $workerId): bool
    {
        return ($this->current()['id'] ?? null) === $workerId;
    }

    /** Quand le dernier worker a demarre, ou nul si aucun n'a jamais demarre. */
    public function lastStartedAt(): ?CarbonImmutable
    {
        try {
            $startedAt = $this->current()['started_at'] ?? null;

            return is_string($startedAt) ? CarbonImmutable::parse($startedAt) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        $stored = json_decode((string) $this->settings->get(self::KEY), true);

        return is_array($stored) ? $stored : [];
    }
}
