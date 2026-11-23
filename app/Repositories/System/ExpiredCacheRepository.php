<?php

declare(strict_types=1);

namespace App\Repositories\System;

use Illuminate\Database\DatabaseManager;

/**
 * Les lignes expirees du cache en base (production : CACHE_STORE=database). Laravel n'efface une ligne expiree
 * que lorsqu'il relit la meme cle, ce qui n'arrive presque jamais pour une limite de debit par visiteur ou une
 * recherche de photos : sans purge, la table grossit sans fin entre deux deploiements (audit du 23/11). Une
 * requete par table.
 */
final readonly class ExpiredCacheRepository
{
    /**
     * @param  string  $table  la table du cache (cache.stores.database.table)
     * @param  string  $lockTable  la table des verrous du cache
     */
    public function __construct(
        private DatabaseManager $database,
        private ?string $connection,
        private string $table,
        private string $lockTable,
    ) {}

    /** @return array{entries: int, locks: int} */
    public function count(int $now): array
    {
        return [
            'entries' => $this->database->connection($this->connection)->table($this->table)->where('expiration', '<', $now)->count(),
            'locks' => $this->database->connection($this->connection)->table($this->lockTable)->where('expiration', '<', $now)->count(),
        ];
    }

    /** @return array{entries: int, locks: int} */
    public function delete(int $now): array
    {
        return [
            'entries' => $this->database->connection($this->connection)->table($this->table)->where('expiration', '<', $now)->delete(),
            'locks' => $this->database->connection($this->connection)->table($this->lockTable)->where('expiration', '<', $now)->delete(),
        ];
    }
}
