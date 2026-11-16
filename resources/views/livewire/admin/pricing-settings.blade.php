{{-- Tarifs des packs, sur page blanche : une section, la phrase sur le prorata en description, les deux tarifs
     l'un sous l'autre, puis le bouton. Une seule colonne (retour de Micha du 16/11 au soir). --}}
<div>
    <x-ui::page-header :title="__('Tarifs')" :description="__('Tarifs annuels des packs Creator et Pro, modifiables sans redéploiement.')" class="mb-8" />

    <x-admin.section :title="__('Tarifs annuels')"
        :description="__('La première année est facturée au prorata (de la signature à la fin de l\'année). Le tarif est le montant annuel plein, aussi utilisé pour les renouvellements.')">
        <form wire:submit="save" class="max-w-md space-y-4 pt-6">
            <x-ui::form-group :label="__('Pack Creator (€ par an)')" for="creator-price" :error="$errors->first('creatorPrice')">
                <x-ui::input type="number" step="0.01" min="1" id="creator-price" wire:model="creatorPrice" :error="$errors->has('creatorPrice')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Pack Pro (€ par an)')" for="pro-price" :error="$errors->first('proPrice')">
                <x-ui::input type="number" step="0.01" min="1" id="pro-price" wire:model="proPrice" :error="$errors->has('proPrice')" />
            </x-ui::form-group>
            <x-ui::button type="submit" :loading="true" target="save">{{ __('Enregistrer les tarifs') }}</x-ui::button>
        </form>
    </x-admin.section>
</div>
