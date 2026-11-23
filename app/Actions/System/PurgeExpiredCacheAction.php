<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Repositories\System\ExpiredCacheRepository;

/**
 * Efface les lignes expirees du cache en base et de ses verrous (audit du 23/11). En simulation, les compte.
 */
final readonly class PurgeExpiredCacheAction
{
    public function __construct(private ExpiredCacheRepository $cache) {}

    /** @return array{entries: int, locks: int} */
    public function execute(bool $dryRun = false): array
    {
        return $dryRun ? $this->cache->count(time()) : $this->cache->delete(time());
    }
}
