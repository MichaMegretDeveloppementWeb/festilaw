<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ __('Mon compte') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Gérez votre adresse email et votre mot de passe.') }}</p>
    </div>

    <div class="grid gap-6">
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3.5">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Adresse email') }}</h2>
            </div>
            <div class="p-5">
                <form wire:submit="updateEmail" class="space-y-4">
                    <div>
                        <label for="profile-email" class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                        <input id="profile-email" type="email" wire:model="email" autocomplete="email"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @error('email') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="updateEmail"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="updateEmail">{{ __('Enregistrer') }}</span>
                            <span wire:loading wire:target="updateEmail">{{ __('Enregistrement') }}&hellip;</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3.5">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Mot de passe') }}</h2>
            </div>
            <div class="p-5">
                <form wire:submit="updatePassword" class="space-y-4">
                    <div>
                        <label for="current-password" class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Mot de passe actuel') }}</label>
                        <input id="current-password" type="password" wire:model="current_password" autocomplete="current-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @error('current_password') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new-password" class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Nouveau mot de passe') }}</label>
                        <input id="new-password" type="password" wire:model="password" autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @error('password') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new-password-confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Confirmer le nouveau mot de passe') }}</label>
                        <input id="new-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="updatePassword">{{ __('Mettre à jour le mot de passe') }}</span>
                            <span wire:loading wire:target="updatePassword">{{ __('Mise à jour') }}&hellip;</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
