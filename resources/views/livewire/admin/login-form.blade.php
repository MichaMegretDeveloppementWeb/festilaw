<div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-base font-bold text-white">F</span>
            <span class="text-lg font-semibold tracking-tight text-slate-900">Festilaw</span>
            <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Admin</span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-lg font-semibold text-slate-900">{{ __('Connexion') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Connectez-vous pour gérer les dossiers.') }}</p>

            <form wire:submit="login" novalidate class="mt-6 space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="admin-email">{{ __('Email') }}</label>
                    <input id="admin-email" type="email" wire:model="email" autocomplete="username" autofocus
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="admin-password">{{ __('Mot de passe') }}</label>
                    <input id="admin-password" type="password" wire:model="password" autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                </div>

                @error('email') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                @error('password') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="login">{{ __('Se connecter') }}</span>
                    <span wire:loading wire:target="login">{{ __('Connexion') }}&hellip;</span>
                </button>
            </form>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                {{ __('Retour au site') }}
            </a>
        </div>
    </div>
</div>
