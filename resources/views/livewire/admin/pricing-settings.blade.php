<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="{{ __('Tarifs') }}" description="{{ __('Tarifs annuels des packs Creator et Pro, modifiables sans redéploiement.') }}" class="mb-6" />

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-xl border border-base bg-surface">
            <div class="border-b border-subtle px-5 py-3.5">
                <h2 class="text-[13px] font-semibold text-primary">{{ __('Pack Creator') }}</h2>
            </div>
            <div class="p-5">
                <x-ui.form-group label="{{ __('Tarif annuel (€)') }}" for="creator-price" :error="$errors->first('creatorPrice')">
                    <x-ui.input type="number" step="0.01" min="1" id="creator-price" wire:model="creatorPrice" :error="$errors->has('creatorPrice')" />
                </x-ui.form-group>
            </div>
        </section>

        <section class="rounded-xl border border-base bg-surface">
            <div class="border-b border-subtle px-5 py-3.5">
                <h2 class="text-[13px] font-semibold text-primary">{{ __('Pack Pro') }}</h2>
            </div>
            <div class="p-5">
                <x-ui.form-group label="{{ __('Tarif annuel (€)') }}" for="pro-price" :error="$errors->first('proPrice')">
                    <x-ui.input type="number" step="0.01" min="1" id="pro-price" wire:model="proPrice" :error="$errors->has('proPrice')" />
                </x-ui.form-group>
            </div>
        </section>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[12px] leading-relaxed text-muted">{{ __('La première année est facturée au prorata (de la signature à la fin de l\'année). Le tarif est le montant annuel plein, aussi utilisé pour les renouvellements.') }}</p>
            <x-ui.button type="submit" :loading="true" target="save" class="shrink-0">{{ __('Enregistrer les tarifs') }}</x-ui.button>
        </div>
    </form>
</div>
