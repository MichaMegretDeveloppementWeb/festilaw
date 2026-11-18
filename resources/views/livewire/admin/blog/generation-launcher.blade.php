{{-- Le bouton « Générer un article » et sa fenetre. Le bouton lit d'abord les images par defaut des reglages
     (prepare), puis ouvre la fenetre ; le lancement redirige vers la page de la generation. --}}
<div class="flex items-center gap-x-2">
    <x-ui::button data-launch-generation x-on:click="$wire.prepare().then(() => $dispatch('ui-open-modal', 'launch-generation'))">
        <x-ui::icon name="sparkles" class="ai-icon h-4 w-4" /> {{ __('Générer un article') }}
    </x-ui::button>

    <x-ui::modal name="launch-generation" :title="__('Générer un article')">
        <form wire:submit="launch" id="launch-generation-form" class="space-y-4">
            <p class="text-[13px] leading-relaxed text-secondary">{{ __('Laissez le sujet et les consignes vides si vous voulez que l\'IA choisisse le sujet.') }}</p>

            <x-ui::form-group :label="__('Sujet (facultatif)')" for="generation-topic" :error="$errors->first('topicHint')">
                <x-ui::input id="generation-topic" wire:model.live.debounce.500ms="topicHint" maxlength="300" :error="$errors->has('topicHint')"
                    :placeholder="__('Par exemple : l\'étiquetage des jouets vendus sur Etsy')" />
            </x-ui::form-group>

            <x-ui::form-group :label="__('Consignes (facultatif)')" for="generation-instructions" :error="$errors->first('instructions')"
                :hint="__('Vous pouvez préciser l\'angle, le lecteur visé ou un point à traiter.')">
                <x-ui::textarea id="generation-instructions" wire:model="instructions" rows="3" maxlength="2000" :error="$errors->has('instructions')" />
            </x-ui::form-group>

            <x-ui::form-group :label="__('Longueur du texte')" for="generation-article-words" :error="$errors->first('articleWords')">
                <x-ui::select id="generation-article-words" wire:model.live="articleWords" :options="$lengthOptions" :error="$errors->has('articleWords')" />
            </x-ui::form-group>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui::form-group :label="__('Origine des images')" for="generation-image-mode" :error="$errors->first('imageMode')">
                    <x-ui::select id="generation-image-mode" wire:model.live="imageMode" :options="$imageModes" :error="$errors->has('imageMode')" />
                </x-ui::form-group>

                <div x-show="$wire.imageMode !== 'none'">
                    <x-ui::form-group :label="__('Nombre d\'images')" for="generation-max-images" :error="$errors->first('maxImages')">
                        <x-ui::select id="generation-max-images" wire:model.live="maxImages" :options="$imageCounts" :error="$errors->has('maxImages')" />
                    </x-ui::form-group>
                </div>
            </div>

            @if ($forecast !== null)
                <div class="space-y-4" data-launch-forecast>
                    @include('livewire.admin.blog.generation.cost-forecast', [
                        'forecast' => $forecast,
                        'advice' => __('Selon l\'estimation, cet article peut coûter jusqu\'à :high, au-delà de :limit. Baissez la longueur. Si même :min mots ne passent pas, choisissez d\'autres modèles ou relevez le plafond par article dans les réglages.', ['high' => $forecast['high'], 'limit' => $forecast['limit'], 'min' => $minWords]),
                    ])
                </div>
            @endif

            @if ($monthlyWarning)
                @include('livewire.admin.blog.generation.monthly-warning', ['warning' => $monthlyWarning])
            @endif

            <p class="text-[12px] leading-relaxed text-muted">{{ __('La génération démarre en quelques secondes et dure plusieurs minutes. Vous pouvez quitter la page, un e-mail vous préviendra quand elle sera terminée.') }}</p>
        </form>

        <x-slot:footer>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'launch-generation')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="submit" form="launch-generation-form" :loading="true" target="launch" data-confirm-launch :disabled="$forecast['exceeds'] ?? false">
                <x-ui::icon name="sparkles" /> {{ __('Lancer la génération') }}
            </x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</div>
