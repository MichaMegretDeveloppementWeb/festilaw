<?php

declare(strict_types=1);

namespace App\Data\Admin;

/**
 * Filtres de la liste back-office (dossiers ou prises de contact). DTO d'entree passe du composant
 * Livewire au service de listing.
 */
final readonly class SubmissionListFilters
{
    public function __construct(
        public bool $contactsMode,
        public string $type,
        public string $state,
        public string $search,
    ) {}
}
