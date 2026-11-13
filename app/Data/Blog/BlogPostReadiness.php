<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Etat de preparation d'un article : ce qui empeche de le publier ou de le programmer (bloquants) et
 * ce qu'il vaudrait mieux completer (alertes, non bloquantes).
 */
final readonly class BlogPostReadiness
{
    /**
     * @param  list<BlogReadinessIssue>  $blockers
     * @param  list<BlogReadinessIssue>  $warnings
     */
    public function __construct(
        public array $blockers,
        public array $warnings,
    ) {}

    public function isReady(): bool
    {
        return $this->blockers === [];
    }

    /** Liste lisible de ce qui bloque, par exemple "titre (espagnol), image principale". */
    public function blockerSummary(): string
    {
        return implode(', ', array_map(static fn (BlogReadinessIssue $issue): string => $issue->label(), $this->blockers));
    }

    public function hasBlocker(string $field, ?string $locale = null): bool
    {
        return $this->has($this->blockers, $field, $locale);
    }

    public function hasWarning(string $field, ?string $locale = null): bool
    {
        return $this->has($this->warnings, $field, $locale);
    }

    /**
     * @param  list<BlogReadinessIssue>  $issues
     */
    private function has(array $issues, string $field, ?string $locale): bool
    {
        foreach ($issues as $issue) {
            if ($issue->field === $field && $issue->locale === $locale) {
                return true;
            }
        }

        return false;
    }
}
