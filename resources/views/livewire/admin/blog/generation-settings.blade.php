{{-- Reglages de la generation par IA, sur page blanche. Chaque section garde l'ordre demande par Micha (son
     titre et sa phrase en haut, un filet, puis ses champs), sans cadre : <x-admin.section>. Les sections prennent
     toute la largeur ; le budget et l'e-mail se rangent cote a cote sur un grand ecran. Un modele se choisit dans
     une liste, un sujet se saisit puis devient une pastille. Aucune cle d'API ici. --}}
<div class="flex min-h-[calc(100svh-6.5rem)] flex-col sm:min-h-[calc(100svh-7.5rem)]">
    @include('livewire.admin.blog.generation.header', ['page' => 'settings'])

    <form wire:submit="save" id="generation-settings-form" class="space-y-12 pb-10">
        <x-admin.section data-settings-card :title="__('Génération automatique')"
            :description="__('Une fois par période, un lot d\'articles est généré sans intervention. Vous recevez un e-mail quand il est prêt à relire.')">
            <div class="space-y-6 pt-6">
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
        </x-admin.section>

        <x-admin.section data-settings-card :title="__('Article par défaut')"
            :description="__('Longueur et images appliquées aux générations automatiques. La fenêtre « Générer un article » les propose aussi, et elles se changent alors pour un seul article.')">
            <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-3">
                <x-ui::form-group :label="__('Longueur du texte (mots)')" for="ai-article-words" :error="$errors->first('articleWords')"
                    :hint="__('De :min à :max mots. Un mot compte environ 6 caractères, soit 6 000 caractères pour 1 000 mots.', ['min' => $minWords, 'max' => number_format($maxWords, 0, ',', ' ')])">
                    <x-ui::input type="number" step="50" :min="$minWords" :max="$maxWords" id="ai-article-words" wire:model.live.debounce.500ms="articleWords" :error="$errors->has('articleWords')" />
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

        <x-admin.section data-settings-card :title="__('Budget')"
            :description="__('Deux plafonds, en dollars, la monnaie dans laquelle le fournisseur d\'IA facture. Celui du mois bloque tout lancement une fois atteint. Celui par article retient tout article dont le coût estimé peut le dépasser.')">
            <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                <x-ui::form-group :label="__('Plafond de dépense du mois ($)')" for="ai-spend-cap" :error="$errors->first('monthlySpendCap')"
                    :hint="__('Une fois ce montant dépensé dans le mois, plus rien ne se lance jusqu\'au mois suivant.')">
                    <x-ui::input type="number" step="0.01" min="0" id="ai-spend-cap" wire:model="monthlySpendCap" :error="$errors->has('monthlySpendCap')" />
                </x-ui::form-group>
                <x-ui::form-group :label="__('Plafond de dépense par article ($)')" for="ai-article-spend-cap" :error="$errors->first('articleSpendCap')"
                    :hint="__('Un article ne se lance que si le haut de sa fourchette de coût tient sous ce montant. Pendant la génération, aucun appel à l\'IA ne part s\'il risque de le dépasser.')">
                    <x-ui::input type="number" step="0.01" min="0.01" id="ai-article-spend-cap" wire:model.live.debounce.500ms="articleSpendCap" :error="$errors->has('articleSpendCap')" />
                </x-ui::form-group>
            </div>
        </x-admin.section>

        <x-admin.section data-settings-card :title="__('E-mail')"
            :description="__('Adresse qui reçoit un e-mail à la fin de chaque lancement, réussi ou non.')">
            <div class="grid grid-cols-1 gap-5 pt-6 sm:grid-cols-2">
                <x-ui::form-group :label="__('Adresse e-mail')" for="ai-notification-email" :error="$errors->first('notificationEmail')">
                    <x-ui::input type="email" id="ai-notification-email" wire:model="notificationEmail" :error="$errors->has('notificationEmail')" />
                </x-ui::form-group>
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

        <x-admin.section data-settings-card data-cost-forecast :title="__('Coût estimé d\'un article')"
            :description="__('Calculé d\'après la longueur, les images et les modèles choisis plus haut, et recalculé à chaque changement. Un article ne se lance, à la main ou automatiquement, que si le haut de la fourchette tient sous le plafond par article.')">
            <div class="space-y-5 pt-6">
                @include('livewire.admin.blog.generation.cost-forecast', [
                    'forecast' => $forecast,
                    'advice' => __('Un article peut coûter jusqu\'à :high, au-dessus du plafond de :cap. Avec ces réglages, aucun article ne partira, ni à la main ni automatiquement. Baissez la longueur, choisissez des modèles moins chers ou relevez le plafond par article.', ['high' => $forecast['high'], 'cap' => $forecast['cap']]),
                ])
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
