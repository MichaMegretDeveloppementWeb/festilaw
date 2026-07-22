<div class="flex min-h-screen items-center justify-center bg-page px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-900 text-base font-bold text-white">F</span>
            <span class="text-lg font-semibold tracking-tight text-primary">Festilaw</span>
            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-secondary">Admin</span>
        </div>

        <div class="rounded-xl border border-base bg-surface p-8">
            <h1 class="text-lg font-semibold text-primary">{{ __('Connexion') }}</h1>
            <p class="mt-1 text-[13px] text-secondary">{{ __('Connectez-vous pour gérer les dossiers.') }}</p>

            <form wire:submit="login" novalidate class="mt-6 space-y-4">
                <x-ui.form-group label="{{ __('Email') }}" for="admin-email">
                    <x-ui.input id="admin-email" type="email" wire:model="email" autocomplete="username" autofocus />
                </x-ui.form-group>

                <x-ui.form-group label="{{ __('Mot de passe') }}" for="admin-password">
                    <x-ui.input id="admin-password" type="password" wire:model="password" autocomplete="current-password" />
                </x-ui.form-group>

                @error('email') <p class="text-[12px] text-red-500">{{ $message }}</p> @enderror
                @error('password') <p class="text-[12px] text-red-500">{{ $message }}</p> @enderror

                <x-ui.button type="submit" :loading="true" target="login" class="w-full justify-center py-2.5">
                    {{ __('Se connecter') }}
                </x-ui.button>
            </form>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-secondary transition hover:text-primary">
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                {{ __('Retour au site') }}
            </a>
        </div>
    </div>
</div>
