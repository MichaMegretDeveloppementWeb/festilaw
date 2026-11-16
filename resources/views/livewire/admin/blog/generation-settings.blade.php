{{-- Reglages de la generation par IA. Chaque section est une carte du back-office (<x-admin.panel>) : son
     titre et sa phrase en haut, un filet, puis ses champs. Les cartes prennent toute la largeur de la page, et
     deux petites cartes se rangent cote a cote sur un grand ecran. Un modele se choisit dans une liste, un
     sujet se saisit puis devient une pastille. Aucune cle d'API ici : elle ne regarde pas la personne qui regle. --}}
<div class="flex min-h-[calc(100svh-6.5rem)] flex-col sm:min-h-[calc(100svh-7.5rem)]">
    @include('livewire.admin.blog.generation.header', ['page' => 'settings'])

    <form wire:submit="save" id="generation-settings-form" class="space-y-6 pb-8">
        <x-admin.panel data-settings-card :title="__('Génération automatique')"
            :description="__('Une fois par période, un lot d\'articles est généré sans intervention. Vous recevez un e-mail quand il est prêt à relire.')">
            <div class="space-y-6 p-5">
                <x-ui::checkbox id="ai-enabled" wire:model="enabled" :label="__('Activer la génération automatique')"
                    :description="__('Tant qu\'elle est désactivée, seuls vos lancements manuels produisent des articles.')" />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui::form-group :label="__('Un lot tous les')" for="ai-period-count" :error="$errors->first('periodCount')">
                        <x-ui::input type="number" min="1" max="365" id="ai-period-count" wire:model="periodCount" :error="$errors->has('periodCount')" />
                    </x-ui::form-group>

                    <x-ui::form-group :label="__('Unité')" for="ai-period-unit" :error="$errors->first('periodUnit')">
                        <x-ui::select id="ai-period-unit" wire:model="periodUnit" :error="$errors->has('periodUnit')"
                            :options="collect($periodUnits)->mapWithKeys(fn ($unit) => [$unit->value => $unit->label()])->all()" />
                    </x-ui::form-group>

                    <x-ui::form-group :label="__('Articles par lot')" for="ai-articles-per-run" :error="$errors->first('articlesPerRun')">
                        <x-ui::input type="number" min="1" max="10" id="ai-articles-per-run" wire:model="articlesPerRun" :error="$errors->has('articlesPerRun')" />
                    </x-ui::form-group>

                    <x-ui::form-group :label="__('Brouillons en attente, au plus')" for="ai-pending-drafts-cap" :error="$errors->first('pendingDraftsCap')">
                        <x-ui::input type="number" min="1" max="50" id="ai-pending-drafts-cap" wire:model="pendingDraftsCap" :error="$errors->has('pendingDraftsCap')" />
                    </x-ui::form-group>
                </div>

                <p class="text-[12px] leading-relaxed text-muted">{{ __('Tant que ce nombre de brouillons générés attend votre relecture, la génération automatique se met en pause. Vos lancements manuels ne sont pas concernés.') }}</p>
            </div>
        </x-admin.panel>

        <x-admin.panel data-settings-card :title="__('Images par défaut')"
            :description="__('Appliquées aux générations automatiques. La fenêtre « Générer un article » les propose aussi, et elles se changent alors pour un seul article.')">
            <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
                <x-ui::form-group :label="__('Origine des images')" for="ai-image-mode" :error="$errors->first('imageMode')">
                    <x-ui::select id="ai-image-mode" wire:model="imageMode" :error="$errors->has('imageMode')" :options="$imageModes" />
                </x-ui::form-group>

                <div x-show="$wire.imageMode !== 'none'">
                    <x-ui::form-group :label="__('Nombre d\'images')" for="ai-max-images" :error="$errors->first('maxImages')">
                        <x-ui::select id="ai-max-images" wire:model="maxImages" :error="$errors->has('maxImages')" :options="$imageCounts" />
                    </x-ui::form-group>
                </div>
            </div>
        </x-admin.panel>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-admin.panel data-settings-card :title="__('Budget')"
                :description="__('Une fois ce montant dépensé dans le mois, plus rien ne se lance jusqu\'au mois suivant.')">
                <div class="p-5">
                    <x-ui::form-group :label="__('Plafond de dépense du mois ($)')" for="ai-spend-cap" :error="$errors->first('monthlySpendCap')"
                        :hint="__('En dollars, la monnaie dans laquelle le fournisseur d\'IA facture. Une génération déjà commencée va au bout.')">
                        <x-ui::input type="number" step="0.01" min="0" id="ai-spend-cap" wire:model="monthlySpendCap" :error="$errors->has('monthlySpendCap')" />
                    </x-ui::form-group>
                </div>
            </x-admin.panel>

            <x-admin.panel data-settings-card :title="__('E-mail')"
                :description="__('Adresse qui reçoit un e-mail à la fin de chaque lancement, réussi ou non.')">
                <div class="p-5">
                    <x-ui::form-group :label="__('Adresse e-mail')" for="ai-notification-email" :error="$errors->first('notificationEmail')">
                        <x-ui::input type="email" id="ai-notification-email" wire:model="notificationEmail" :error="$errors->has('notificationEmail')" />
                    </x-ui::form-group>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel data-settings-card :title="__('Sujets')"
            :description="__('Sujets que l\'IA doit privilégier, et sujets qu\'elle doit laisser de côté.')">
            <div class="grid grid-cols-1 gap-6 p-5 lg:grid-cols-2">
                <x-ui::form-group :label="__('Sujets et mots-clés à privilégier')" for="ai-priority-topics"
                    :error="$errors->first('priorityTopics') ?: $errors->first('priorityTopics.*')">
                    <x-admin.tag-input id="ai-priority-topics" wire:model="priorityTopics" :placeholder="__('Saisissez un sujet, puis appuyez sur Entrée')"
                        :max="\App\Data\Blog\Generation\BlogAiSettings::MAX_TOPICS" :max-length="\App\Data\Blog\Generation\BlogAiSettings::TOPIC_MAX_LENGTH"
                        :error="$errors->has('priorityTopics') || $errors->has('priorityTopics.*')" />
                </x-ui::form-group>

                <x-ui::form-group :label="__('Sujets à éviter')" for="ai-avoided-topics"
                    :error="$errors->first('avoidedTopics') ?: $errors->first('avoidedTopics.*')">
                    <x-admin.tag-input id="ai-avoided-topics" wire:model="avoidedTopics" :placeholder="__('Saisissez un sujet, puis appuyez sur Entrée')"
                        :max="\App\Data\Blog\Generation\BlogAiSettings::MAX_TOPICS" :max-length="\App\Data\Blog\Generation\BlogAiSettings::TOPIC_MAX_LENGTH"
                        :error="$errors->has('avoidedTopics') || $errors->has('avoidedTopics.*')" />
                </x-ui::form-group>
            </div>
        </x-admin.panel>

        <x-admin.panel data-settings-card :title="__('Modèles')"
            :description="__('Modèle utilisé à chaque étape. Les deux prix sont ceux d\'un million de tokens lus et d\'un million de tokens écrits.')">
            <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($modelLabels as $step => $label)
                    <x-ui::form-group :label="$label" :for="'ai-model-'.$step" :error="$errors->first('models.'.$step)" wire:key="ai-model-{{ $step }}">
                        <x-ui::select :id="'ai-model-'.$step" wire:model="models.{{ $step }}" :options="$modelOptions[$step]" :error="$errors->has('models.'.$step)" />
                    </x-ui::form-group>
                @endforeach
            </div>
        </x-admin.panel>

        <x-admin.panel data-settings-card :title="__('Mention de transparence')"
            :description="__('Phrase affichée en bas des articles générés, pour indiquer aux lecteurs qu\'une IA a aidé à les préparer. Si le champ est vide, rien n\'est affiché.')">
            <div class="grid grid-cols-1 gap-5 p-5 lg:grid-cols-3">
                @foreach ($transparencyNotice as $locale => $notice)
                    <x-ui::form-group :label="__('En :language', ['language' => $localeNames[$locale] ?? $locale])" :for="'ai-notice-'.$locale"
                        :error="$errors->first('transparencyNotice.'.$locale)" wire:key="ai-notice-{{ $locale }}">
                        <x-ui::textarea :id="'ai-notice-'.$locale" wire:model="transparencyNotice.{{ $locale }}" rows="3" maxlength="500"
                            :error="$errors->has('transparencyNotice.'.$locale)" />
                    </x-ui::form-group>
                @endforeach
            </div>
        </x-admin.panel>
    </form>

    <x-admin.action-bar>
        <div class="flex items-center justify-end gap-3">
            <x-ui::button type="submit" form="generation-settings-form" :loading="true" target="save">{{ __('Enregistrer les réglages') }}</x-ui::button>
        </div>
    </x-admin.action-bar>

    @assets
        @vite('resources/js/admin/tag-input.js')
    @endassets
</div>
