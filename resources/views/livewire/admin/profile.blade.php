<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="{{ __('Mon compte') }}" description="{{ __('Gérez votre adresse email et votre mot de passe.') }}" class="mb-6" />

    <div class="grid gap-6">
        <section class="rounded-xl border border-base bg-surface">
            <div class="border-b border-subtle px-5 py-3.5">
                <h2 class="text-[13px] font-semibold text-primary">{{ __('Adresse email') }}</h2>
            </div>
            <div class="p-5">
                <form wire:submit="updateEmail" class="space-y-4">
                    <x-ui.form-group label="{{ __('Email') }}" for="profile-email" :error="$errors->first('email')">
                        <x-ui.input id="profile-email" type="email" wire:model="email" autocomplete="email" :error="$errors->has('email')" />
                    </x-ui.form-group>
                    <div class="flex justify-end">
                        <x-ui.button type="submit" :loading="true" target="updateEmail">{{ __('Enregistrer') }}</x-ui.button>
                    </div>
                </form>
            </div>
        </section>

        <section class="rounded-xl border border-base bg-surface">
            <div class="border-b border-subtle px-5 py-3.5">
                <h2 class="text-[13px] font-semibold text-primary">{{ __('Mot de passe') }}</h2>
            </div>
            <div class="p-5">
                <form wire:submit="updatePassword" class="space-y-4">
                    <x-ui.form-group label="{{ __('Mot de passe actuel') }}" for="current-password" :error="$errors->first('current_password')">
                        <x-ui.input id="current-password" type="password" wire:model="current_password" autocomplete="current-password" :error="$errors->has('current_password')" />
                    </x-ui.form-group>
                    <x-ui.form-group label="{{ __('Nouveau mot de passe') }}" for="new-password" :error="$errors->first('password')">
                        <x-ui.input id="new-password" type="password" wire:model="password" autocomplete="new-password" :error="$errors->has('password')" />
                    </x-ui.form-group>
                    <x-ui.form-group label="{{ __('Confirmer le nouveau mot de passe') }}" for="new-password-confirmation">
                        <x-ui.input id="new-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
                    </x-ui.form-group>
                    <div class="flex justify-end">
                        <x-ui.button type="submit" :loading="true" target="updatePassword">{{ __('Mettre à jour le mot de passe') }}</x-ui.button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
