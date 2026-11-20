<?php

declare(strict_types=1);

namespace App\Data\System;

use Carbon\CarbonImmutable;

/**
 * Une sorte d'erreur du journal du serveur : les entrees de meme fichier, niveau, message et classe
 * d'exception, comptees ensemble. Les nombres variables (identifiants, durees) ne separent pas deux
 * entrees. Le message d'exception et l'endroit sont ceux de la premiere entree, en exemple.
 */
final readonly class ErrorLogGroup
{
    /**
     * @param  string  $file  le fichier du journal, sans sa date (laravel.log, payments.log)
     * @param  string  $lastFile  le fichier ou chercher la derniere occurrence (laravel-2026-11-21.log)
     * @param  string  $level  ERROR, CRITICAL, ALERT ou EMERGENCY
     * @param  list<string>  $routes  les pages ou l'erreur est arrivee, quand le journal les donne
     */
    public function __construct(
        public string $file,
        public string $lastFile,
        public string $level,
        public string $message,
        public ?string $exceptionClass,
        public ?string $exceptionMessage,
        public ?string $location,
        public array $routes,
        public int $count,
        public CarbonImmutable $firstAt,
        public CarbonImmutable $lastAt,
    ) {}
}
