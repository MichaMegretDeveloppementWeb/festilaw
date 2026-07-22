<!DOCTYPE html>
<html lang="fr" class="h-full bg-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', 'Back-office · Festilaw')</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    @uiKitHead
    @vite(['resources/css/ui-kit.css', 'resources/css/admin.css', 'resources/js/ui-kit.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="h-full text-primary antialiased">
    @auth
        @php($routeSubmission = request()->route('submission'))
        @php($viewingContact = $routeSubmission instanceof \App\Models\Submission && $routeSubmission->type === \App\Enums\Submission\SubmissionType::Contact)
        @php($isProfile = request()->routeIs('admin.profile'))
        @php($isQuiz = request()->routeIs('admin.quiz.*'))
        @php($isContacts = request()->routeIs('admin.contacts.*') || $viewingContact)
        @php($isDossiers = request()->routeIs('admin.submissions.*') && ! $viewingContact)

        <div x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false" class="md:hidden">
            <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false" class="fixed inset-0 z-30 bg-gray-900/20"></div>

            <div class="sticky top-0 z-40 border-b border-base bg-surface">
                <div class="flex items-center justify-between px-4 py-3">
                    <a href="{{ route('admin.submissions.index') }}" class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-900 text-sm font-bold text-white">F</span>
                        <span class="text-[13px] font-semibold text-primary">Festilaw</span>
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-secondary">Admin</span>
                    </a>
                    <button type="button" @click="mobileOpen = ! mobileOpen" :aria-expanded="mobileOpen" aria-label="{{ __('Menu') }}"
                        class="-mr-1.5 rounded-lg p-1.5 text-secondary transition hover:bg-elevated">
                        <x-ui.icon x-show="! mobileOpen" name="bars-3" class="h-6 w-6" />
                        <x-ui.icon x-show="mobileOpen" x-cloak name="x-mark" class="h-6 w-6" />
                    </button>
                </div>
                <div x-show="mobileOpen" x-cloak x-transition class="absolute inset-x-0 top-full z-40 border-t border-base bg-surface px-3 py-3">
                    <nav class="space-y-1">
                        <a href="{{ route('admin.submissions.index') }}" @click="mobileOpen = false" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium',
                            'bg-gray-100 text-gray-900' => $isDossiers,
                            'text-secondary hover:bg-elevated hover:text-primary' => ! $isDossiers,
                        ])>
                            <x-ui.icon name="rectangle-stack" class="h-[18px] w-[18px]" />
                            {{ __('Dossiers') }}
                        </a>
                        <a href="{{ route('admin.contacts.index') }}" @click="mobileOpen = false" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium',
                            'bg-gray-100 text-gray-900' => $isContacts,
                            'text-secondary hover:bg-elevated hover:text-primary' => ! $isContacts,
                        ])>
                            <x-ui.icon name="envelope" class="h-[18px] w-[18px]" />
                            {{ __('Prises de contact') }}
                        </a>
                        <a href="{{ route('admin.quiz.index') }}" @click="mobileOpen = false" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium',
                            'bg-gray-100 text-gray-900' => $isQuiz,
                            'text-secondary hover:bg-elevated hover:text-primary' => ! $isQuiz,
                        ])>
                            <x-ui.icon name="question-mark-circle" class="h-[18px] w-[18px]" />
                            {{ __('Quiz') }}
                        </a>
                        <a href="{{ route('home') }}" target="_blank" rel="noopener" @click="mobileOpen = false"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-secondary hover:bg-elevated hover:text-primary">
                            <x-ui.icon name="arrow-top-right-on-square" class="h-[18px] w-[18px]" />
                            {{ __('Voir le site public') }}
                        </a>
                    </nav>
                    <div class="mt-3 border-t border-base pt-3">
                        <a href="{{ route('admin.profile') }}" @click="mobileOpen = false" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium',
                            'bg-gray-100 text-gray-900' => $isProfile,
                            'text-secondary hover:bg-elevated hover:text-primary' => ! $isProfile,
                        ])>
                            <x-ui.icon name="user-circle" class="h-[18px] w-[18px]" />
                            {{ __('Mon compte') }}
                        </a>
                        <div class="mb-2 mt-1 px-3 text-[12px] text-muted">{{ auth()->user()->email }}</div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-secondary hover:bg-elevated hover:text-primary">
                                <x-ui.icon name="arrow-right-start-on-rectangle" class="h-[18px] w-[18px]" />
                                {{ __('Se déconnecter') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <aside class="admin-sidebar fixed inset-y-0 left-0 z-30 hidden flex-col overflow-hidden border-r border-base bg-surface md:flex">
            <div class="admin-navitem flex h-16 items-center gap-2 border-b border-base">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-sm font-bold text-white">F</span>
                <span class="admin-navlabel text-[15px] font-semibold tracking-tight text-primary">Festilaw</span>
                <span class="admin-navlabel rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-secondary">Admin</span>
            </div>
            <nav class="flex-1 space-y-1 py-3">
                <a href="{{ route('admin.submissions.index') }}" @class([
                    'admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium transition',
                    'bg-gray-100 text-gray-900' => $isDossiers,
                    'text-secondary hover:bg-elevated hover:text-primary' => ! $isDossiers,
                ])>
                    <x-ui.icon name="rectangle-stack" class="h-[18px] w-[18px] shrink-0" />
                    <span class="admin-navlabel">{{ __('Dossiers') }}</span>
                </a>
                <a href="{{ route('admin.contacts.index') }}" @class([
                    'admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium transition',
                    'bg-gray-100 text-gray-900' => $isContacts,
                    'text-secondary hover:bg-elevated hover:text-primary' => ! $isContacts,
                ])>
                    <x-ui.icon name="envelope" class="h-[18px] w-[18px] shrink-0" />
                    <span class="admin-navlabel">{{ __('Prises de contact') }}</span>
                </a>
                <a href="{{ route('admin.quiz.index') }}" @class([
                    'admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium transition',
                    'bg-gray-100 text-gray-900' => $isQuiz,
                    'text-secondary hover:bg-elevated hover:text-primary' => ! $isQuiz,
                ])>
                    <x-ui.icon name="question-mark-circle" class="h-[18px] w-[18px] shrink-0" />
                    <span class="admin-navlabel">{{ __('Quiz') }}</span>
                </a>
            </nav>
            <div class="mt-auto border-t border-base py-3">
                <a href="{{ route('admin.profile') }}" @class([
                    'admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium transition',
                    'bg-gray-100 text-gray-900' => $isProfile,
                    'text-secondary hover:bg-elevated hover:text-primary' => ! $isProfile,
                ])>
                    <x-ui.icon name="user-circle" class="h-[18px] w-[18px] shrink-0" />
                    <span class="admin-navlabel">{{ __('Mon compte') }}</span>
                </a>
                <a href="{{ route('home') }}" target="_blank" rel="noopener"
                    class="admin-navitem flex items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium text-secondary transition hover:bg-elevated hover:text-primary">
                    <x-ui.icon name="arrow-top-right-on-square" class="h-[18px] w-[18px] shrink-0" />
                    <span class="admin-navlabel">{{ __('Voir le site public') }}</span>
                </a>
                <div class="admin-navlabel px-3 py-1.5 text-[12px] text-muted" title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="admin-navitem flex w-full items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium text-secondary transition hover:bg-elevated hover:text-primary">
                        <x-ui.icon name="arrow-right-start-on-rectangle" class="h-[18px] w-[18px] shrink-0" />
                        <span class="admin-navlabel">{{ __('Se déconnecter') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-content">
            <main class="mx-auto max-w-[90em] px-4 py-6 sm:px-6 sm:py-8">
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

    {{-- Toast ephemere : retour d'action (evenement admin-toast + flash de session apres redirect). --}}
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
        class="fixed bottom-6 right-6 z-50 w-[320px] max-w-[calc(100vw-3rem)] overflow-hidden rounded-xl border border-base bg-surface py-3.5 pl-4 pr-10 shadow-lg"
        :class="type === 'error' ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-emerald-500'"
        role="status"
        aria-live="polite"
        style="display: none;"
    >
        <p class="text-[13px] font-medium text-primary" x-text="msg"></p>
        <button type="button" class="absolute right-2 top-2 text-muted transition hover:text-secondary" @click="show = false" aria-label="{{ __('Fermer') }}">
            <x-ui.icon name="x-mark" class="h-4 w-4" />
        </button>
        <span class="absolute bottom-0 left-0 h-0.5 w-full origin-left" :class="type === 'error' ? 'bg-red-500' : 'bg-emerald-500'" x-ref="bar"></span>
    </div>

    @livewireScripts
</body>
</html>
