{{-- Reglages de la generation par IA, sur page blanche, en trois groupes (decision de Micha du 18/11) : ce qui
     vaut pour chaque article, les valeurs par defaut que la fenetre de lancement propose, et ce qui ne concerne
     que la generation automatique. Melanges, on ne savait plus ce qui s'appliquait a quoi. Chaque section garde
     son titre et sa phrase en haut, un filet, puis ses champs (<x-admin.section>). Un modele et une longueur se
     choisissent dans une liste, un sujet se saisit puis devient une pastille. Aucune cle d'API ici. --}}
<div class="flex min-h-[calc(100svh-6.5rem)] flex-col sm:min-h-[calc(100svh-7.5rem)]">
    @include('livewire.admin.blog.generation.header', ['page' => 'settings'])

    <form wire:submit="save" id="generation-settings-form" class="space-y-16 pb-10">
        <x-admin.section-group data-settings-group="common" :title="__('Réglages communs')"
            :description="__('Ils valent pour chaque article généré, lancé à la main ou automatiquement.')">
            <x-admin.section data-settings-card :title="__('Modèles')"
                :description="__('Modèle utilisé à chaque étape. Les deux prix sont ceux d\'un million de tokens lus et d\'un million de tokens écrits.')">
                <div class="grid grid-cols-1 gap-5 pt-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($modelLabels as $step => $label)
                        <x-ui::form-group :label="$label" :for="'ai-model-'.$step" :error="$errors->first('models.'.$step)" wire:key="ai-model-{{ $step }}">
                            <x-ui::select :id="'ai-model-'.$step" wire:model.live="models.{{ $step }}" :options="$modelOptions[$step]" :error="$errors->has('models.'.$step)" />
                        </x-ui::form-group>
                    @endforeach
                </div>
            </x-admin.section>

            <x-admin.section data-settings-card :title="__('Sujets')"
                :description="__('Sujets que l\'IA doit privilégier, et sujets qu\'elle doit laisser de côté.')">
                <div class="grid grid-cols-1 gap-6 pt-6 lg:grid-cols-2">
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
            </x-admin.section>

            <x-admin.section data-settings-card :title="__('Budget')"
                :description="__('Deux plafonds, en dollars, la monnaie dans laquelle le fournisseur d\'IA facture. Celui par article retient tout article dont le coût estimé dépasse ce plafond augmenté de :percent %. Celui du mois ne fait qu\'avertir.', ['percent' => $forecast['percent']])">
                <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                    <x-ui::form-group :label="__('Plafond de dépense du mois ($)')" for="ai-spend-cap" :error="$errors->first('monthlySpendCap')"
                        :hint="__('Ce plafond ne bloque rien. Vous êtes averti à l\'écran et par e-mail quand la dépense du mois en atteint :percent %, puis quand elle le dépasse.', ['percent' => $warningPercent])">
                        <x-ui::input type="number" step="0.01" min="0" id="ai-spend-cap" wire:model.live.debounce.500ms="monthlySpendCap" :error="$errors->has('monthlySpendCap')" />
                    </x-ui::form-group>
                    <x-ui::form-group :label="__('Plafond de dépense par article ($)')" for="ai-article-spend-cap" :error="$errors->first('articleSpendCap')"
                        :hint="__('Un article ne se lance que si le haut de son coût estimé tient sous ce montant augmenté de :percent %. Pendant la génération, aucun appel à l\'IA ne part s\'il risque de dépasser cette même limite.', ['percent' => $forecast['percent']])">
                        <x-ui::input type="number" step="0.01" min="0.01" id="ai-article-spend-cap" wire:model.live.debounce.500ms="articleSpendCap" :error="$errors->has('articleSpendCap')" />
                    </x-ui::form-group>
                </div>

                @if ($monthlyWarning)
                    @include('livewire.admin.blog.generation.monthly-warning', ['warning' => $monthlyWarning, 'class' => 'mt-6'])
                @endif
            </x-admin.section>

            <x-admin.section data-settings-card :title="__('E-mail')"
                :description="__('Adresse qui reçoit un e-mail à la fin de chaque lancement, réussi ou non.')">
                <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                    <x-ui::form-group :label="__('Adresse e-mail')" for="ai-notification-email" :error="$errors->first('notificationEmail')">
                        <x-ui::input type="email" id="ai-notification-email" wire:model="notificationEmail" :error="$errors->has('notificationEmail')" />
                    </x-ui::form-group>
                </div>
            </x-admin.section>

            <x-admin.section data-settings-card :title="__('Mention de transparence')"
                :description="__('Phrase affichée en bas des articles générés, pour indiquer aux lecteurs qu\'une IA a aidé à les préparer. Si le champ est vide, rien n\'est affiché.')">
                <div class="grid grid-cols-1 gap-5 pt-6 lg:grid-cols-3">
                    @foreach ($transparencyNotice as $locale => $notice)
                        <x-ui::form-group :label="__('En :language', ['language' => $localeNames[$locale] ?? $locale])" :for="'ai-notice-'.$locale"
                            :error="$errors->first('transparencyNotice.'.$locale)" wire:key="ai-notice-{{ $locale }}">
                            <x-ui::textarea :id="'ai-notice-'.$locale" wire:model="transparencyNotice.{{ $locale }}" rows="3" maxlength="500"
                                :error="$errors->has('transparencyNotice.'.$locale)" />
                        </x-ui::form-group>
                    @endforeach
                </div>
            </x-admin.section>
        </x-admin.section-group>

        <x-admin.section-group data-settings-group="defaults" :title="__('Valeurs par défaut')"
            :description="__('La génération automatique les reprend. La fenêtre « Générer un article » les propose, et vous pouvez les changer pour un article.')">
            <x-admin.section data-settings-card data-settings-length :title="__('Longueur et images')">
                <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-3">
                    <x-ui::form-group :label="__('Longueur du texte')" for="ai-article-words" :error="$errors->first('articleWords')">
                        <x-ui::select id="ai-article-words" wire:model.live="articleWords" :options="$lengthOptions" :error="$errors->has('articleWords')" />
                    </x-ui::form-group>

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

            <x-admin.section data-settings-card data-cost-forecast :title="__('Coût estimé d\'un article')"
                :description="__('Calculé d\'après la longueur, les images et les modèles choisis, et recalculé à chaque changement. Un article ne se lance, à la main ou automatiquement, que si le haut de la fourchette tient sous le plafond par article augmenté de :percent %.', ['percent' => $forecast['percent']])">
                <div class="space-y-5 pt-6">
                    @include('livewire.admin.blog.generation.cost-forecast', [
                        'forecast' => $forecast,
                        'advice' => __('Selon l\'estimation, un article peut coûter jusqu\'à :high, au-delà de :limit. Avec ces réglages, aucun article ne partira, ni à la main ni automatiquement. Baissez la longueur, choisissez des modèles moins chers ou relevez le plafond par article.', ['high' => $forecast['high'], 'limit' => $forecast['limit']]),
                    ])
                </div>
            </x-admin.section>
        </x-admin.section-group>

        <x-admin.section-group data-settings-group="automatic" :title="__('Génération automatique')"
            :description="__('Elle ne concerne que les lots lancés sans vous.')">
            <x-admin.section data-settings-card :title="__('Activation et rythme')"
                :description="__('Une fois par période, un lot d\'articles est généré sans intervention. Vous recevez un e-mail quand il est prêt à relire.')">
                <div class="space-y-6 pt-6">
                    <x-ui::checkbox id="ai-enabled" wire:model="enabled" :label="__('Activer la génération automatique')"
                        :description="__('Tant qu\'elle est désactivée, seuls vos lancements manuels produisent des articles.')" />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
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
                    </div>
                </div>
            </x-admin.section>

            <x-admin.section data-settings-card :title="__('Brouillons en attente')"
                :description="__('Tant que ce nombre de brouillons générés attend votre relecture, la génération automatique se met en pause. Vos lancements manuels ne sont pas concernés.')">
                <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-3">
                    <x-ui::form-group :label="__('Brouillons en attente, au plus')" for="ai-pending-drafts-cap" :error="$errors->first('pendingDraftsCap')">
                        <x-ui::input type="number" min="1" max="50" id="ai-pending-drafts-cap" wire:model="pendingDraftsCap" :error="$errors->has('pendingDraftsCap')" />
                    </x-ui::form-group>
                </div>
            </x-admin.section>
        </x-admin.section-group>
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
