<?php

declare(strict_types=1);

namespace App\Data\Admin;

/**
 * Un groupe de liens du menu du back-office. Le premier groupe n'a pas de titre.
 */
final readonly class AdminNavGroup
{
    /**
     * @param  list<AdminNavItem>  $items
     */
    public function __construct(
        public ?string $title,
        public array $items,
    ) {}
}
