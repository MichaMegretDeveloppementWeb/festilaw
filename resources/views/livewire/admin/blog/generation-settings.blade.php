{{-- Reglages de la generation par IA, sur page blanche. Sept sections a la suite, sans titres de groupe, dans
     l'ordre d'un recit (decision de Micha du 19/11) : comment un article est fait (modeles, longueur et images,
     cout estime), ce qu'il coute dans le mois, qui est prevenu, la cle OpenRouter (decision du 23/11, composant a
     part avec son propre bouton), puis la generation automatique, qui reprend tout ce qui precede. Elle vient en
     dernier car ses champs n'apparaissent que case cochee : rien n'est en dessous, donc rien ne bouge (regle « une
     action ne transforme jamais la page sous les yeux »). Chaque section garde son titre et sa phrase en haut, un
     filet, puis ses champs (<x-admin.section>). --}}
@php
    $rhythmFields = ['periodCount', 'periodUnit', 'articlesPerRun', 'pendingDraftsCap'];
    $rhythmHasError = $errors->hasAny($rhythmFields);
@endphp
<div class="flex min-h-[calc(100svh-6.5rem)] flex-col sm:min-h-[calc(100svh-7.5rem)]">
    @include('livewire.admin.blog.generation.header', ['page' => 'settings'])

    @if ($loadFailed)
        <x-ui::load-failure :title="__('Le chargement des réglages a échoué.')"
            :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="reloadPage" />
    @else
    <form wire:submit="save" id="generation-settings-form" class="pb-10">
        <x-admin.section data-settings-section="models" :spaced="false" :title="__('Modèles')"
            :description="__('Le modèle d\'IA de chaque étape, pour tous les articles, lancés par vous ou automatiquement. Les deux prix sont ceux d\'un million de tokens lus et d\'un million de tokens écrits.')">
            <div class="grid grid-cols-1 gap-5 pt-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($modelLabels as $step => $label)
                    <x-ui::form-group :label="$label" :for="'ai-model-'.$step" :error="$errors->first('models.'.$step)" wire:key="ai-model-{{ $step }}">
                        <x-ui::select :id="'ai-model-'.$step" wire:model.live="models.{{ $step }}" :options="$modelOptions[$step]" :error="$errors->has('models.'.$step)" />
                    </x-ui::form-group>
                @endforeach
            </div>

            @include('livewire.admin.blog.generation.recommended-models', ['recommended' => $modelsAreRecommended])
        </x-admin.section>

        <x-admin.section data-settings-section="length" :title="__('Longueur, images et effort')"
            :description="__('Les valeurs que propose la fenêtre « Générer un article ». Vous pouvez les changer pour un article.')">
            <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                <x-ui::form-group :label="__('Longueur du texte')" for="ai-article-words" :error="$errors->first('articleWords')">
                    <x-ui::select id="ai-article-words" wire:model.live="articleWords" :options="$lengthOptions" :error="$errors->has('articleWords')" />
                </x-ui::form-group>

                @include('livewire.admin.blog.generation.effort-field', ['id' => 'ai-effort'])

                <x-ui::form-group :label="__('Origine des images')" for="ai-image-mode" :error="$errors->first('imageMode')">
                    <x-ui::select id="ai-image-mode" wire:model.live="imageMode" :error="$errors->has('imageMode')" :options="$imageModes" />
                </x-ui::form-group>

                <div x-show="$wire.imageMode !== 'none'">
                    <x-ui::form-group :label="__('Nombre d\'images')" for="ai-max-images" :error="$errors->first('maxImages')">
                        <x-ui::select id="ai-max-images" wire:model.live="maxImages" :error="$errors->has('maxImages')" :options="$imageCounts" />
                    </x-ui::form-group>
                </div>
            </div>
        </x-admin.section>

        <x-admin.section data-settings-section="forecast" data-cost-forecast :title="__('Coût estimé d\'un article')"
            :description="__('Calculé d\'après les modèles et leurs prix du jour, la longueur et les images choisis, et recalculé à chaque changement. Chaque génération affiche ensuite son coût réel à côté de cette estimation.')">
            <div class="space-y-5 pt-6">
                @include('livewire.admin.blog.generation.cost-forecast', ['forecast' => $forecast])
            </div>
        </x-admin.section>

        <x-admin.section data-settings-section="budget" :title="__('Budget du mois')"
            :description="__('Le plafond se règle en dollars, la monnaie dans laquelle le fournisseur d\'IA facture.')">
            <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                <x-ui::form-group :label="__('Plafond de dépense du mois ($)')" for="ai-spend-cap" :error="$errors->first('monthlySpendCap')"
                    :hint="__('Dépasser le plafond ne bloque pas la génération. Vous êtes averti à l\'écran et par e-mail quand la dépense du mois en atteint :percent %, puis quand elle le dépasse.', ['percent' => $warningPercent])">
                    <x-ui::input type="number" step="0.01" min="1" max="10000" id="ai-spend-cap" wire:model.live.debounce.500ms="monthlySpendCap" :error="$errors->has('monthlySpendCap')" />
                </x-ui::form-group>
            </div>

            @if ($monthlyWarning)
                @include('livewire.admin.blog.generation.monthly-warning', ['warning' => $monthlyWarning, 'class' => 'mt-6'])
            @endif
        </x-admin.section>

        <x-admin.section data-settings-section="email" :title="__('E-mails')"
            :description="__('L\'adresse qui reçoit un e-mail à la fin de chaque lancement, réussi ou non, et les avertissements du budget.')">
            <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                <x-ui::form-group :label="__('Adresse e-mail')" for="ai-notification-email" :error="$errors->first('notificationEmail')">
                    <x-ui::input type="email" id="ai-notification-email" wire:model="notificationEmail" :error="$errors->has('notificationEmail')" />
                </x-ui::form-group>
            </div>
        </x-admin.section>

        <livewire:admin.blog.open-router-key-form />

        <x-admin.section id="generation-automatique" data-settings-section="automatic" :title="__('Génération automatique')"
            :description="__('L\'IA choisit un sujet et prépare des articles à intervalles réguliers, sans que vous ayez à les lancer. Elle reprend les modèles, la longueur et les images réglés plus haut.')">
            <div class="space-y-6 pt-6">
                <x-ui::checkbox id="ai-enabled" wire:model="enabled" :label="__('Activer la génération automatique')"
                    :description="__('Tant qu\'elle est désactivée, seuls vos lancements produisent des articles.')" />

                {{-- Ces champs ne servent que si la generation automatique est activee (Micha, 19/11). Un champ en
                     erreur garde le bloc visible, pour qu'un refus ne passe jamais inapercu. --}}
                <div class="space-y-6" data-automatic-fields x-show="$wire.enabled || @js($rhythmHasError)" @unless ($enabled || $rhythmHasError) x-cloak @endunless>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <x-ui::form-group :label="__('Nombre d\'articles')" for="ai-articles-per-run" :error="$errors->first('articlesPerRun')">
                            <x-ui::input type="number" min="1" max="10" id="ai-articles-per-run" wire:model="articlesPerRun" :error="$errors->has('articlesPerRun')" />
                        </x-ui::form-group>

                        <x-ui::form-group :label="__('Tous les')" for="ai-period-count" :error="$errors->first('periodCount')">
                            <x-ui::input type="number" min="1" max="12" id="ai-period-count" wire:model="periodCount" :error="$errors->has('periodCount')" />
                        </x-ui::form-group>

                        <x-ui::form-group :label="__('Unité')" for="ai-period-unit" :error="$errors->first('periodUnit')">
                            <x-ui::select id="ai-period-unit" wire:model="periodUnit" :error="$errors->has('periodUnit')"
                                :options="collect($periodUnits)->mapWithKeys(fn ($unit) => [$unit->value => $unit->label()])->all()" />
                        </x-ui::form-group>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <x-ui::form-group :label="__('Brouillons en attente, au plus')" for="ai-pending-drafts-cap" :error="$errors->first('pendingDraftsCap')"
                            :hint="__('Quand ce nombre de brouillons générés attend votre relecture, la génération automatique se met en pause. Vos lancements ne sont pas concernés.')">
                            <x-ui::input type="number" min="1" max="50" id="ai-pending-drafts-cap" wire:model="pendingDraftsCap" :error="$errors->has('pendingDraftsCap')" />
                        </x-ui::form-group>
                    </div>
                </div>
            </div>
        </x-admin.section>
    </form>

    <x-admin.action-bar>
        <div class="flex items-center justify-end gap-3">
            <x-ui::button type="submit" form="generation-settings-form" :loading="true" target="save">{{ __('Enregistrer les réglages') }}</x-ui::button>
        </div>
    </x-admin.action-bar>
    @endif
</div>
