<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="{{ __('Tarifs') }}" description="{{ __('Tarifs annuels des packs Creator et Pro, modifiables sans redéploiement.') }}" class="mb-6" />

    {{-- Astuce test : baisser momentanement le prix pour une recette de paiement a moindre cout. --}}
    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-[13px] leading-relaxed text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-400">
        {{ __('Pour tester un paiement de bout en bout à moindre coût, mettez un tarif à 1 €, faites votre test, puis rétablissez le vrai tarif ici. Le changement est immédiat (paiement, prorata, affichage, contrat, renouvellement).') }}
    </div>

    <form wire:submit="save">
        <section class="rounded-xl border border-base bg-surface">
            <div class="border-b border-subtle px-5 py-3.5">
                <h2 class="text-[13px] font-semibold text-primary">{{ __('Abonnement annuel') }}</h2>
            </div>
            <div class="space-y-5 p-5">
                <x-ui.form-group label="{{ __('Pack Creator (€ / an)') }}" for="creator-price"
                    hint="{{ __('Tarif par défaut : :price €', ['price' => $creatorDefault]) }}" :error="$errors->first('creatorPrice')">
                    <x-ui.input type="number" step="0.01" min="1" id="creator-price" wire:model="creatorPrice" :error="$errors->has('creatorPrice')" />
                </x-ui.form-group>

                <x-ui.form-group label="{{ __('Pack Pro (€ / an)') }}" for="pro-price"
                    hint="{{ __('Tarif par défaut : :price €', ['price' => $proDefault]) }}" :error="$errors->first('proPrice')">
                    <x-ui.input type="number" step="0.01" min="1" id="pro-price" wire:model="proPrice" :error="$errors->has('proPrice')" />
                </x-ui.form-group>

                <p class="text-[12px] text-muted">{{ __('La première année est facturée au prorata (de la signature à la fin de l\'année). Le tarif ci-dessus est le montant annuel plein, aussi utilisé pour les renouvellements.') }}</p>
            </div>
            <div class="flex justify-end border-t border-subtle px-5 py-3.5">
                <x-ui.button type="submit" :loading="true" target="save">{{ __('Enregistrer les tarifs') }}</x-ui.button>
            </div>
        </section>
    </form>
</div>
