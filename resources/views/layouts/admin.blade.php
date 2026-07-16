<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', 'Back-office · Festilaw')</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    @livewireStyles
    @vite('resources/css/admin.css')
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    @auth
        @php($isContacts = request()->routeIs('admin.contacts.*'))
        @php($isDossiers = request()->routeIs('admin.submissions.*'))

        {{-- Barre superieure mobile + menu burger (le menu se superpose au contenu, sans le decaler) --}}
        <div x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false" class="md:hidden">
            {{-- Fond semi-transparent : recouvre le contenu et ferme au clic --}}
            <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false" class="fixed inset-0 z-30 bg-slate-900/20"></div>

            <div class="sticky top-0 z-40 border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between px-4 py-3">
                    <a href="{{ route('admin.submissions.index') }}" class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-500 text-sm font-bold text-white">F</span>
                        <span class="text-sm font-semibold text-slate-900">Festilaw</span>
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Admin</span>
                    </a>
                    <button type="button" @click="mobileOpen = ! mobileOpen" :aria-expanded="mobileOpen" aria-label="{{ __('Menu') }}"
                        class="-mr-1.5 rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100">
                        <svg x-show="! mobileOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        <svg x-show="mobileOpen" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                {{-- Menu deroulant : positionne en absolute, il passe PAR-DESSUS le contenu --}}
                <div x-show="mobileOpen" x-cloak x-transition class="absolute inset-x-0 top-full z-40 border-t border-slate-200 bg-white px-3 py-3 shadow-lg">
                    <nav class="space-y-1">
                        <a href="{{ route('admin.submissions.index') }}" @click="mobileOpen = false" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium',
                            'bg-brand-50 text-brand-700' => $isDossiers,
                            'text-slate-600 hover:bg-slate-100' => ! $isDossiers,
                        ])>
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v4H4z"/><path d="M4 10h16v10H4z"/><path d="M9 14h6"/></svg>
                            {{ __('Dossiers') }}
                        </a>
                        <a href="{{ route('admin.contacts.index') }}" @click="mobileOpen = false" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium',
                            'bg-brand-50 text-brand-700' => $isContacts,
                            'text-slate-600 hover:bg-slate-100' => ! $isContacts,
                        ])>
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                            {{ __('Prises de contact') }}
                        </a>
                        <a href="{{ route('home') }}" target="_blank" rel="noopener" @click="mobileOpen = false"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            {{ __('Voir le site public') }}
                        </a>
                    </nav>
                    <div class="mt-3 border-t border-slate-200 pt-3">
                        <div class="mb-2 px-3 text-xs text-slate-500">{{ auth()->user()->email }}</div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                {{ __('Se déconnecter') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar (>=md) : rail replie qui se deploie au survol, plein en >=lg --}}
        <aside class="admin-sidebar fixed inset-y-0 left-0 z-30 hidden flex-col overflow-hidden border-r border-slate-200 bg-white md:flex">
            <div class="admin-navitem flex h-16 items-center gap-2 border-b border-slate-200">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-sm font-bold text-white">F</span>
                <span class="admin-navlabel text-[15px] font-semibold tracking-tight text-slate-900">Festilaw</span>
                <span class="admin-navlabel rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Admin</span>
            </div>
            <nav class="flex-1 space-y-1 py-3">
                <a href="{{ route('admin.submissions.index') }}" @class([
                    'admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-sm font-medium transition',
                    'bg-brand-50 text-brand-700' => $isDossiers,
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $isDossiers,
                ])>
                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v4H4z"/><path d="M4 10h16v10H4z"/><path d="M9 14h6"/></svg>
                    <span class="admin-navlabel">{{ __('Dossiers') }}</span>
                </a>
                <a href="{{ route('admin.contacts.index') }}" @class([
                    'admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-sm font-medium transition',
                    'bg-brand-50 text-brand-700' => $isContacts,
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $isContacts,
                ])>
                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                    <span class="admin-navlabel">{{ __('Prises de contact') }}</span>
                </a>
            </nav>
            <div class="mt-auto border-t border-slate-200 py-3">
                <a href="{{ route('home') }}" target="_blank" rel="noopener"
                    class="admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    <span class="admin-navlabel">{{ __('Voir le site public') }}</span>
                </a>
                <div class="admin-navlabel px-3 py-1.5 text-xs text-slate-500" title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="admin-navitem flex w-full items-center gap-2.5 rounded-lg py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span class="admin-navlabel">{{ __('Se déconnecter') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-content">
            <main class="max-w-7xl px-4 py-6 md:px-8 md:py-8">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    @else
        <main>
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    @endauth

    {{-- Toast ephemere : retour d'action (succes/erreur) qui s'efface tout seul, avec une barre de
         temps restant. Ecoute l'evenement Livewire admin-toast et le flash de session (apres redirect). --}}
    @php($serverFlash = session('admin_flash'))
    <div
        x-data="{
            show: false,
            msg: '',
            type: 'success',
            duration: 4500,
            timer: null,
            fire(message, type) {
                if (! message) { return; }
                this.msg = message;
                this.type = type || 'success';
                this.show = true;
                clearTimeout(this.timer);
                this.$nextTick(() => {
                    const bar = this.$refs.bar;
                    if (bar) {
                        bar.style.animation = 'none';
                        void bar.offsetWidth;
                        bar.style.animation = 'admin-toast-bar ' + this.duration + 'ms linear forwards';
                    }
                });
                this.timer = setTimeout(() => { this.show = false; }, this.duration);
            }
        }"
        x-init="fire(@js($serverFlash), 'success')"
        @admin-toast.window="fire($event.detail.message, $event.detail.type)"
        x-show="show"
        x-transition.opacity.duration.200ms
        x-cloak
        class="fixed bottom-6 right-6 z-50 w-[320px] max-w-[calc(100vw-3rem)] overflow-hidden rounded-lg border border-slate-200 bg-white py-3.5 pl-4 pr-10 shadow-lg"
        :class="type === 'error' ? 'border-l-4 border-l-rose-500' : 'border-l-4 border-l-emerald-500'"
        role="status"
        aria-live="polite"
        style="display: none;"
    >
        <p class="text-sm font-medium text-slate-800" x-text="msg"></p>
        <button type="button" class="absolute right-2 top-2 text-slate-400 transition hover:text-slate-600" @click="show = false" aria-label="{{ __('Fermer') }}">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <span class="absolute bottom-0 left-0 h-0.5 w-full origin-left" :class="type === 'error' ? 'bg-rose-500' : 'bg-emerald-500'" x-ref="bar"></span>
    </div>

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>
