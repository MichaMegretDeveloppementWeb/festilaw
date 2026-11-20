<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Data\System\ErrorLogGroup;
use App\Data\System\ErrorLogSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Lit les erreurs du journal du serveur sur une periode, pour le rapport du matin. Parcourt les fichiers de
 * storage/logs modifies pendant la periode (journal par jour ou fichier unique, canaux dedies compris),
 * ligne par ligne, sans jamais charger un fichier entier. Ne garde que l'environnement courant (les tests
 * ecrivent dans les memes fichiers en local) et les niveaux ERROR et plus graves. Le format lu est celui
 * de Laravel : `[2026-11-21 06:00:00] production.ERROR: message {"contexte":...}`, la trace sur les
 * lignes suivantes.
 */
final readonly class ErrorLogReader
{
    private const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] ([\w-]+)\.([A-Z]+): (.*)$/';

    private const EXCEPTION_MARKER = '"exception":"[object] (';

    private const TEXT_LIMIT = 300;

    private const MAX_ROUTES = 3;

    /**
     * @param  string  $directory  le dossier du journal (storage/logs)
     * @param  string  $environment  l'environnement dont on lit les erreurs (APP_ENV)
     * @param  string  $basePath  la racine de l'application, retiree des chemins de fichier
     */
    public function __construct(
        private string $directory,
        private string $environment,
        private string $basePath,
    ) {}

    public function read(CarbonImmutable $since, CarbonImmutable $until): ErrorLogSummary
    {
        $groups = [];
        $unreadable = [];

        foreach ($this->filesTouchedSince($since) as $path) {
            try {
                $this->scan($path, $since, $until, $groups);
            } catch (Throwable) {
                $unreadable[] = basename($path);
            }
        }

        usort($groups, static fn (array $a, array $b): int => [$b['count'], $b['last']] <=> [$a['count'], $a['last']]);

        return new ErrorLogSummary(
            $since,
            $until,
            array_map(static fn (array $group): ErrorLogGroup => new ErrorLogGroup(
                $group['file'],
                $group['lastFile'],
                $group['level'],
                $group['message'],
                $group['exceptionClass'],
                $group['exceptionMessage'],
                $group['location'],
                $group['routes'],
                $group['count'],
                $group['first'],
                $group['last'],
            ), $groups),
            $unreadable,
        );
    }

    /** @return list<string> */
    private function filesTouchedSince(CarbonImmutable $since): array
    {
        $files = glob(rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.'*.log') ?: [];
        $files = array_values(array_filter($files, static fn (string $path): bool => (int) @filemtime($path) >= $since->getTimestamp()));
        sort($files);

        return $files;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function scan(string $path, CarbonImmutable $since, CarbonImmutable $until, array &$groups): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Log file [{$path}] could not be opened.");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if ($line === '' || $line[0] !== '[' || preg_match(self::HEADER, rtrim($line, "\r\n"), $header) !== 1) {
                    continue;
                }

                if ($header[2] !== $this->environment || ! in_array($header[3], self::LEVELS, true)) {
                    continue;
                }

                $at = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $header[1]);

                if (! $at instanceof CarbonImmutable || $at->lessThanOrEqualTo($since) || $at->greaterThan($until)) {
                    continue;
                }

                $this->add($groups, basename($path), $header[3], $header[4], $at);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function add(array &$groups, string $fileName, string $level, string $rest, CarbonImmutable $at): void
    {
        $cut = strpos($rest, ' {"');
        $message = Str::limit($this->withoutBasePath(trim($cut === false ? $rest : substr($rest, 0, $cut))), self::TEXT_LIMIT);
        [$class, $exceptionMessage, $location] = $this->exception($rest);
        $file = (string) preg_replace('/-\d{4}-\d{2}-\d{2}(?=\.log$)/', '', $fileName);
        $key = md5(implode("\0", [$file, $level, $this->shape($message), (string) $class]));

        $group = $groups[$key] ?? [
            'file' => $file,
            'lastFile' => $fileName,
            'level' => $level,
            'message' => $message,
            'exceptionClass' => $class,
            'exceptionMessage' => $exceptionMessage,
            'location' => $location,
            'routes' => [],
            'count' => 0,
            'first' => $at,
            'last' => $at,
        ];

        $group['count']++;

        if ($at->greaterThanOrEqualTo($group['last'])) {
            $group['last'] = $at;
            $group['lastFile'] = $fileName;
        }

        if ($at->lessThan($group['first'])) {
            $group['first'] = $at;
        }

        $route = preg_match('/"route":"([^"]*)"/', $rest, $match) === 1 ? $this->unescape($match[1]) : null;

        if ($route !== null && $route !== '' && ! in_array($route, $group['routes'], true) && count($group['routes']) < self::MAX_ROUTES) {
            $group['routes'][] = $route;
        }

        $groups[$key] = $group;
    }

    /**
     * La classe, le message et l'endroit de l'exception journalisee sur la ligne d'en-tete. Le message
     * d'une exception sur plusieurs lignes n'a que sa premiere ligne, sans l'endroit.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function exception(string $rest): array
    {
        $start = strpos($rest, self::EXCEPTION_MARKER);

        if ($start === false) {
            return [null, null, null];
        }

        $tail = substr($rest, $start + strlen(self::EXCEPTION_MARKER));

        if (preg_match('/^(.+?)\(code: [^)]*\): (.*) at (.+):(\d+)\)$/', $tail, $match) === 1) {
            return [$this->unescape($match[1]), Str::limit($this->withoutBasePath($this->unescape($match[2])), self::TEXT_LIMIT), $this->withoutBasePath($this->unescape($match[3])).':'.$match[4]];
        }

        if (preg_match('/^(.+?)\(code: [^)]*\): (.*)$/', $tail, $match) === 1) {
            return [$this->unescape($match[1]), Str::limit($this->withoutBasePath($this->unescape($match[2])), self::TEXT_LIMIT), null];
        }

        return [null, null, null];
    }

    /** Deux messages qui ne different que par leurs nombres sont la meme erreur. */
    private function shape(string $text): string
    {
        return (string) preg_replace('/\d+/', '#', $text);
    }

    private function unescape(string $text): string
    {
        return str_replace(['\\\\', '\\"', '\\/'], ['\\', '"', '/'], $text);
    }

    /** Retire la racine de l'application des chemins cites, ecrits avec l'un ou l'autre separateur. */
    private function withoutBasePath(string $text): string
    {
        $base = rtrim(str_replace('\\', '/', $this->basePath), '/');

        return str_replace([$base.'/', str_replace('/', '\\', $base).'\\'], '', $text);
    }
}
