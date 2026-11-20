<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Data\Admin\AdminNavItem;
use App\Data\Admin\AdminNavSection;
use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;

/**
 * Le menu principal du back-office, decrit une seule fois : la mise en page l'affiche deux fois (menu
 * mobile et barre laterale) a partir de cette liste. Calcul pur, aucune requete.
 *
 * Un ecran seul a sa ligne et son icone ; un espace de plusieurs pages est une section repliable, comme
 * sur Chouchoute-toi (demande de Micha du 20/11). Audience et Marketing sont les ecrans de Falcon
 * Analytics : leurs noms de routes sont fixes, seules leurs adresses se reglent.
 */
final readonly class AdminNavigation
{
    /**
     * @param  bool  $searchConsoleConfigured  un client Search Console est regle : sans lui, l'ecran des
     *                                         integrations n'a rien a proposer et reste hors du menu
     */
    public function __construct(
        private UrlGenerator $url,
        private bool $searchConsoleConfigured = false,
    ) {}

    /**
     * @param  string|null  $routeName  nom de la route courante
     * @param  bool  $viewingContact  la fiche ouverte est une prise de contact (meme route qu'un dossier)
     * @return list<AdminNavItem|AdminNavSection>
     */
    public function entries(?string $routeName, bool $viewingContact): array
    {
        $is = static fn (string ...$patterns): bool => $routeName !== null && Str::is($patterns, $routeName);

        return [
            new AdminNavItem(__('Dossiers'), $this->url->route('admin.submissions.index'), 'rectangle-stack', $is('admin.submissions.*') && ! $viewingContact),
            new AdminNavItem(__('Prises de contact'), $this->url->route('admin.contacts.index'), 'envelope', $is('admin.contacts.*') || $viewingContact),
            new AdminNavItem(__('Quiz'), $this->url->route('admin.quiz.index'), 'question-mark-circle', $is('admin.quiz.*')),
            new AdminNavItem(__('Tarifs'), $this->url->route('admin.settings'), 'currency-euro', $is('admin.settings')),
            $this->section('blog', __('Blog'), 'newspaper', $is('admin.blog.*'), $is, [
                [__('Articles'), 'admin.blog.posts.index', ['admin.blog.posts.*']],
                [__('Catégories'), 'admin.blog.categories.index', ['admin.blog.categories.*']],
                [__('Génération IA'), 'admin.blog.generation.index', ['admin.blog.generation.*']],
            ]),
            $this->section('audience', __('Audience'), 'chart-pie', $is('analytics.admin.*') && ! $is('analytics.admin.marketing.*'), $is, [
                [__('Vue d\'ensemble'), 'analytics.admin.overview', ['analytics.admin.overview']],
                [__('Temps réel'), 'analytics.admin.realtime', ['analytics.admin.realtime']],
                [__('Visiteurs'), 'analytics.admin.visitors', ['analytics.admin.visitors*']],
                [__('Sessions'), 'analytics.admin.sessions', ['analytics.admin.sessions*']],
                [__('Événements'), 'analytics.admin.events', ['analytics.admin.events']],
                [__('Tunnels'), 'analytics.admin.funnels', ['analytics.admin.funnels']],
                ...($this->searchConsoleConfigured ? [[__('Intégrations'), 'analytics.admin.integrations', ['analytics.admin.integrations*']]] : []),
            ]),
            $this->section('marketing', __('Marketing'), 'megaphone', $is('analytics.admin.marketing.*'), $is, [
                [__('Vue d\'ensemble'), 'analytics.admin.marketing.dashboard', ['analytics.admin.marketing.dashboard']],
                [__('Campagnes'), 'analytics.admin.marketing.campaigns', ['analytics.admin.marketing.campaigns*']],
                [__('Publicités'), 'analytics.admin.marketing.ads', ['analytics.admin.marketing.ads*']],
            ]),
        ];
    }

    /**
     * Une section s'allume sur le prefixe de ses routes, une page sur les siennes : une fiche de visiteur
     * allume « Visiteurs », une adresse inconnue de l'espace n'allume que la section.
     *
     * @param  Closure(string...): bool  $is
     * @param  list<array{0: string, 1: string, 2: list<string>}>  $pages  libelle, route, routes qui allument la page
     */
    private function section(string $name, string $label, string $icon, bool $active, Closure $is, array $pages): AdminNavSection
    {
        $items = array_map(
            fn (array $page): AdminNavItem => new AdminNavItem($page[0], $this->url->route($page[1]), null, $is(...$page[2])),
            $pages,
        );

        return new AdminNavSection($name, $label, $icon, $items[0]->url, $active, $items);
    }
}
