<div class="admin-login">
    <div class="admin-login__card">
        <h1 class="admin-login__title">Festilaw · {{ __('Back-office') }}</h1>
        <p class="admin-login__sub">{{ __('Connectez-vous pour gérer les dossiers.') }}</p>

        <form wire:submit="login" novalidate>
            <div class="admin-field">
                <label class="admin-field__label" for="admin-email">{{ __('Email') }}</label>
                <input id="admin-email" type="email" class="admin-input" wire:model="email" autocomplete="username" autofocus>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="admin-password">{{ __('Mot de passe') }}</label>
                <input id="admin-password" type="password" class="admin-input" wire:model="password" autocomplete="current-password">
            </div>

            @error('email') <p class="admin-error">{{ $message }}</p> @enderror
            @error('password') <p class="admin-error">{{ $message }}</p> @enderror

            <button type="submit" class="admin-btn admin-btn--primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">{{ __('Se connecter') }}</span>
                <span wire:loading wire:target="login">{{ __('Connexion') }}&hellip;</span>
            </button>
        </form>
    </div>
</div>
