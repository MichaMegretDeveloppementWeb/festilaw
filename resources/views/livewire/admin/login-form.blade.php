{{-- Le contenu de la carte de connexion : la coquille « invite » du kit pose la marque et la carte. --}}
<div class="px-1 py-2">
    <h1 class="text-lg font-semibold text-primary">{{ __('Connexion') }}</h1>
    <p class="mt-1 text-[13px] text-secondary">{{ __('Connectez-vous pour gérer les dossiers.') }}</p>

    <form wire:submit="login" novalidate class="mt-6 space-y-4">
        <x-ui::form-group :label="__('Email')" for="admin-email" :error="$errors->first('email')">
            <x-ui::input id="admin-email" type="email" wire:model="email" autocomplete="username" autofocus :error="$errors->has('email')" />
        </x-ui::form-group>

        <x-ui::form-group :label="__('Mot de passe')" for="admin-password" :error="$errors->first('password')">
            <x-ui::input id="admin-password" type="password" wire:model="password" autocomplete="current-password" :error="$errors->has('password')" />
        </x-ui::form-group>

        <x-ui::button type="submit" :loading="true" target="login" class="w-full">
            {{ __('Se connecter') }}
        </x-ui::button>
    </form>

    <div class="mt-5 border-t border-subtle pt-4 text-center">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-secondary transition hover:text-primary">
            <x-ui::icon name="arrow-left" />
            {{ __('Retour au site') }}
        </a>
    </div>
</div>
