<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Data\Admin\AdminNavGroup;
use App\Data\Admin\AdminNavItem;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;

/**
 * Le menu principal du back-office, decrit une seule fois : la mise en page l'affiche deux fois (menu
 * mobile et barre laterale) a partir de cette liste. Calcul pur, aucune requete.
 */
final readonly class AdminNavigation
{
    public function __construct(private UrlGenerator $url) {}

    /**
     * @param  string|null  $routeName  nom de la route courante
     * @param  bool  $viewingContact  la fiche ouverte est une prise de contact (meme route qu'un dossier)
     * @return list<AdminNavGroup>
     */
    public function groups(?string $routeName, bool $viewingContact): array
    {
        $is = static fn (string ...$patterns): bool => $routeName !== null && Str::is($patterns, $routeName);

        return [
            new AdminNavGroup(null, [
                new AdminNavItem(__('Dossiers'), $this->url->route('admin.submissions.index'), 'rectangle-stack', $is('admin.submissions.*') && ! $viewingContact),
                new AdminNavItem(__('Prises de contact'), $this->url->route('admin.contacts.index'), 'envelope', $is('admin.contacts.*') || $viewingContact),
                new AdminNavItem(__('Quiz'), $this->url->route('admin.quiz.index'), 'question-mark-circle', $is('admin.quiz.*')),
                new AdminNavItem(__('Tarifs'), $this->url->route('admin.settings'), 'currency-euro', $is('admin.settings')),
            ]),
            new AdminNavGroup(__('Blog'), [
                new AdminNavItem(__('Articles'), $this->url->route('admin.blog.posts.index'), 'newspaper', $is('admin.blog.posts.*')),
                new AdminNavItem(__('Catégories'), $this->url->route('admin.blog.categories.index'), 'tag', $is('admin.blog.categories.*')),
                new AdminNavItem(__('Génération IA'), $this->url->route('admin.blog.generation.index'), 'sparkles', $is('admin.blog.generation.*')),
            ]),
        ];
    }
}
