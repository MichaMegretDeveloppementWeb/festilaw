{{-- Mise en page du back-office : la notre, pas une coquille du kit Falcon (le kit en propose une, sans
     l'imposer). Elle se declare au kit par ses deux marques : @falconStyles AVANT la feuille de
     l'application, @falconScripts avant </body>. Elle pose elle-meme le conteneur des notifications juste
     avant, en haut (le kit garde le sien) ; resources/css/admin/kit.css le centre (demande de Micha du
     23/11). Rien a regler dans la configuration du kit ; `php artisan ui-kit:check` verifie l'ensemble.
     `lang="fr"` : le back-office est redige en francais, quelle que soit la langue du site public.
     Le titre vient du composant de l'ecran (->title('Dossiers')) : barre du haut et onglet du navigateur.
     $adminNav et $schedulerStatus : fournis par le View::composer de AppServiceProvider. La barre laterale
     reprend celle de Chouchoute-toi (demande de Micha du 20/11) : un ecran seul a sa ligne, un espace de
     plusieurs pages est une section repliable, et le compte est en bas (resources/css/admin/sidebar.css).
     $surface : 'plain' pose le fond blanc sur le contenu (ecrans du blog et de la generation IA, decision du
     16/11/2026, « page blanche a la Stripe ») ; les autres ecrans gardent le fond gris du kit. Un ecran le choisit
     par ->layoutData(['surface' => 'plain']). 'full' laisse toute la place au contenu, sans colonne ni marges :
     les ecrans de Falcon Analytics portent leur propre boite (components/admin/analytics-layout). --}}
@php($pageTitle = $title ?? __('Back-office'))
@php($plainSurface = ($surface ?? 'page') === 'plain')
@php($fullSurface = ($surface ?? 'page') === 'full')
<!DOCTYPE html>
{{-- Une seule classe `class`, le theme du kit dedans, a cote de bg-page (meme element : sinon bande claire). --}}
<html lang="fr" @class(['h-full', 'bg-page' => ! $plainSurface, 'bg-surface' => $plainSurface, falcon_theme_class()])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>{{ $pageTitle }} · {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    @falconStyles

    {{-- La police de la suite (DM Sans), puis la feuille de nos propres vues. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @livewireStyles
</head>
@auth
    <body class="min-h-full text-primary antialiased">
        {{-- Le degrade des icones de l'IA (resources/css/admin/ai.css, classe .ai-icon), dessine une seule fois. --}}
        <svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="ai-gradient" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" style="stop-color: var(--ai-from)" />
                    <stop offset="50%" style="stop-color: var(--ai-via)" />
                    <stop offset="100%" style="stop-color: var(--ai-to)" />
                </linearGradient>
            </defs>
        </svg>

        <x-ui::sidebar.frame>
            <x-slot:bar>
                <x-ui::sidebar brand="Festilaw">
                    <x-slot:logo>
                        <a href="{{ route('admin.submissions.index') }}" aria-label="{{ __('Festilaw, back-office') }}"
                            class="admin-brand-logo flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-sm font-bold text-white dark:bg-white dark:text-gray-900">F</a>
                    </x-slot:logo>

                    <x-ui::sidebar.group>
                        @foreach ($adminNav as $entry)
                            @if ($entry instanceof \App\Data\Admin\AdminNavSection)
                                <x-ui::sidebar.collapsible :name="$entry->name" :label="$entry->label" :icon="$entry->icon" :href="$entry->url" :active="$entry->active">
                                    @foreach ($entry->items as $item)
                                        <x-ui::sidebar.link :href="$item->url" :active="$item->active">{{ $item->label }}</x-ui::sidebar.link>
                                    @endforeach
                                </x-ui::sidebar.collapsible>
                            @else
                                <x-ui::sidebar.link :href="$entry->url" :icon="$entry->icon" :active="$entry->active">{{ $entry->label }}</x-ui::sidebar.link>
                            @endif
                        @endforeach
                    </x-ui::sidebar.group>

                    <x-slot:user>
                        <x-admin.account-menu :name="(string) auth()->user()->name" :email="(string) auth()->user()->email" />
                    </x-slot:user>
                </x-ui::sidebar>
            </x-slot:bar>

            <header class="flex h-14 shrink-0 items-center justify-between gap-x-3 border-b border-default bg-surface px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-x-3">
                    <x-ui::sidebar.trigger />
                    <span class="truncate text-[13px] font-medium text-secondary">{{ $pageTitle }}</span>
                </div>

                <div class="flex shrink-0 items-center gap-x-3">
                    {{-- Etat des taches automatiques (cron), en production seulement. --}}
                    @if ($schedulerStatus)
                        @php($schedulerState = $schedulerStatus['stalled'] ? 'stalled' : ($schedulerStatus['lastRunAt'] ? 'ok' : 'unknown'))
                        <x-ui::badge :dot="true" class="hidden sm:inline-flex" data-scheduler-state="{{ $schedulerState }}"
                            :color="['stalled' => 'red', 'ok' => 'emerald', 'unknown' => 'gray'][$schedulerState]"
                            title="{{ $schedulerStatus['lastRunAt'] ? __('Dernier passage : :date (UTC)', ['date' => $schedulerStatus['lastRunAt']->format('d/m/Y H:i')]) : '' }}">
                            @if ($schedulerStatus['stalled'])
                                {{ __('Tâches automatiques à l\'arrêt') }}
                            @elseif ($schedulerStatus['minutesAgo'] === 0)
                                {{ __('Tâches automatiques · à l\'instant') }}
                            @elseif ($schedulerStatus['lastRunAt'])
                                {{ __('Tâches automatiques · il y a :minutes min', ['minutes' => $schedulerStatus['minutesAgo']]) }}
                            @else
                                {{ __('Tâches automatiques · aucun passage') }}
                            @endif
                        </x-ui::badge>
                    @endif

                    <x-ui::theme-toggle />
                </div>
            </header>

            <main @class(['group/surface flex-1', 'bg-surface' => $plainSurface]) data-surface="{{ $plainSurface ? 'plain' : ($fullSurface ? 'full' : 'page') }}">
                {{-- Page blanche : plus de marge sur les cotes, pour que les filets qui debordent de la colonne
                     (sections, bandes de chiffres, listes) gardent de l'air jusqu'au bord. Surface pleine : aucune
                     colonne, l'ecran se cadre lui-meme. --}}
                <div @class([
                    'mx-auto max-w-[90em] px-4 py-6 sm:py-8' => ! $fullSurface,
                    'sm:px-6' => ! $plainSurface && ! $fullSurface,
                    'sm:px-10 lg:px-12' => $plainSurface,
                ])>
                    @if ($schedulerStatus['stalled'] ?? false)
                        <x-ui::alert type="error" :class="$fullSurface ? 'mx-4 mt-6 mb-6 sm:mx-6' : 'mb-6'" :title="__('Les tâches automatiques ne tournent plus depuis le :date (UTC)', ['date' => $schedulerStatus['lastRunAt']->format('d/m/Y à H:i')])">
                            {{ __('Rappels de renouvellement, vérification des paiements et des signatures, récupération des contrats signés et purges RGPD ne s\'exécutent plus. À vérifier côté hébergement (tâche cron).') }}
                        </x-ui::alert>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </x-ui::sidebar.frame>

        <x-ui::toast position="top-right" />
        @falconScripts
        @livewireScripts
    </body>
@else
    {{-- Connexion : une carte au milieu de l'ecran, sans menu. --}}
    <body class="flex min-h-full items-center justify-center px-4 py-12 text-primary antialiased">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center justify-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-900 text-base font-bold text-white dark:bg-white dark:text-gray-900">F</span>
                <span class="text-lg font-semibold tracking-tight text-primary">Festilaw</span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-secondary dark:bg-gray-800">Admin</span>
            </div>

            <x-ui::card>
                {{ $slot }}
            </x-ui::card>
        </div>

        <x-ui::toast position="top-right" />
        @falconScripts
        @livewireScripts
    </body>
@endauth
</html>
