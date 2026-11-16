{{-- Tarifs des packs, sur page blanche et sur toute la largeur : deux sections cote a cote sur un grand ecran, puis
     la phrase sur le prorata et le bouton d'enregistrement. --}}
<div>
    <x-ui::page-header :title="__('Tarifs')" :description="__('Tarifs annuels des packs Creator et Pro, modifiables sans redéploiement.')" class="mb-8" />

    <form wire:submit="save">
        <div class="grid gap-x-16 gap-y-12 lg:grid-cols-2">
            <x-admin.section :title="__('Pack Creator')">
                <div class="max-w-sm pt-6">
                    <x-ui::form-group :label="__('Tarif annuel (€)')" for="creator-price" :error="$errors->first('creatorPrice')">
                        <x-ui::input type="number" step="0.01" min="1" id="creator-price" wire:model="creatorPrice" :error="$errors->has('creatorPrice')" />
                    </x-ui::form-group>
                </div>
            </x-admin.section>

            <x-admin.section :title="__('Pack Pro')">
                <div class="max-w-sm pt-6">
                    <x-ui::form-group :label="__('Tarif annuel (€)')" for="pro-price" :error="$errors->first('proPrice')">
                        <x-ui::input type="number" step="0.01" min="1" id="pro-price" wire:model="proPrice" :error="$errors->has('proPrice')" />
                    </x-ui::form-group>
                </div>
            </x-admin.section>
        </div>

        <div class="mt-10 flex flex-col gap-4 border-t border-default pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="max-w-2xl text-[13px] leading-relaxed text-muted">{{ __('La première année est facturée au prorata (de la signature à la fin de l\'année). Le tarif est le montant annuel plein, aussi utilisé pour les renouvellements.') }}</p>
            <x-ui::button type="submit" :loading="true" target="save" class="shrink-0">{{ __('Enregistrer les tarifs') }}</x-ui::button>
        </div>
    </form>
</div>
