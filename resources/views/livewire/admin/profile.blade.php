{{-- Mon compte, sur page blanche : l'adresse email puis le mot de passe, en sections l'une sous l'autre, chacune
     avec son bouton. Une seule colonne (retour de Micha du 16/11 au soir : pas deux colonnes pour ces reglages). --}}
<div>
    <x-ui::page-header :title="__('Mon compte')" :description="__('Gérez votre adresse email et votre mot de passe.')" class="mb-8" />

    <div>
        <x-admin.section :title="__('Adresse email')">
            <form wire:submit="updateEmail" class="max-w-md space-y-4 pt-6">
                <x-ui::form-group :label="__('Email')" for="profile-email" :error="$errors->first('email')">
                    <x-ui::input id="profile-email" type="email" wire:model="email" autocomplete="email" :error="$errors->has('email')" />
                </x-ui::form-group>
                <x-ui::button type="submit" :loading="true" target="updateEmail">{{ __('Enregistrer') }}</x-ui::button>
            </form>
        </x-admin.section>

        <x-admin.section :title="__('Mot de passe')">
            <form wire:submit="updatePassword" class="max-w-md space-y-4 pt-6">
                <x-ui::form-group :label="__('Mot de passe actuel')" for="current-password" :error="$errors->first('current_password')">
                    <x-ui::input id="current-password" type="password" wire:model="current_password" autocomplete="current-password" :error="$errors->has('current_password')" />
                </x-ui::form-group>
                <x-ui::form-group :label="__('Nouveau mot de passe')" for="new-password" :error="$errors->first('password')">
                    <x-ui::input id="new-password" type="password" wire:model="password" autocomplete="new-password" :error="$errors->has('password')" />
                </x-ui::form-group>
                <x-ui::form-group :label="__('Confirmer le nouveau mot de passe')" for="new-password-confirmation">
                    <x-ui::input id="new-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
                </x-ui::form-group>
                <x-ui::button type="submit" :loading="true" target="updatePassword">{{ __('Mettre à jour le mot de passe') }}</x-ui::button>
            </form>
        </x-admin.section>
    </div>
</div>
