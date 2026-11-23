{{-- Section « Clé d'accès OpenRouter » des reglages (OpenRouterKeyForm). Elle vit dans le formulaire des reglages,
     sans formulaire a elle : son bouton est de type « button » et la touche Entree du champ lance sa propre
     verification, pour ne jamais enregistrer les autres reglages a sa place. L'ecart avec la section precedente
     est pose ici, sur la racine, car la section est la premiere de son conteneur. --}}
<div class="not-first:mt-16" data-openrouter-key>
    <x-admin.section id="cle-openrouter" :spaced="false" :title="__('Clé d\'accès OpenRouter')"
        :description="__('La clé qui permet au site d\'appeler les modèles d\'IA, créée dans votre compte OpenRouter. Le site la vérifie auprès d\'OpenRouter avant de l\'enregistrer.')">
        <div class="space-y-5 pt-6">
            @error('key-load-failed')
                <x-ui::load-failure compact :title="$message" />
            @else
                <p class="text-[13px] text-secondary" data-key-state>
                    @if ($state->masked)
                        {{ __('Clé actuelle') }} <span class="font-mono text-primary">{{ $state->masked }}</span>.
                    @endif
                    <span @class(['text-amber-700 dark:text-amber-400' => $state->needsAttention])>{{ $state->sentence }}</span>
                </p>
            @enderror

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui::form-group :label="$state?->masked ? __('Nouvelle clé') : __('Clé')" for="ai-openrouter-key" :error="$errors->first('newKey')">
                    <div class="flex items-start gap-3">
                        <x-ui::input id="ai-openrouter-key" class="min-w-0 flex-1 font-mono" autocomplete="off" spellcheck="false"
                            placeholder="sk-or-v1-…" wire:model="newKey" wire:keydown.enter.prevent="save" :error="$errors->has('newKey')" />
                        <x-ui::button type="button" variant="secondary" wire:click="save" :loading="true" target="save">{{ __('Vérifier et enregistrer') }}</x-ui::button>
                    </div>
                </x-ui::form-group>
            </div>
        </div>
    </x-admin.section>
</div>
