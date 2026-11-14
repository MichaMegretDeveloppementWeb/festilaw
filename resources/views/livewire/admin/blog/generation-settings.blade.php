{{-- Reglages de la generation par IA. Aucune cle d'API ici. --}}
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.blog.generation.index') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Génération IA') }}
    </a>

    <x-ui::page-header :title="__('Réglages de la génération IA')"
        :description="__('Ce que l\'IA écrit, à quel rythme et pour quel budget. Un article généré reste un brouillon tant que vous ne l\'avez pas publié.')"
        class="mb-6 mt-2" />

    <form wire:submit="save" class="space-y-6">
        <x-admin.panel :title="__('Génération automatique')"
            :description="__('Une fois par période, un lot d\'articles est généré sans intervention. Vous recevez un e-mail quand il est prêt à relire.')">
            <div class="space-y-5 p-5">
                <x-ui::checkbox id="ai-enabled" wire:model="enabled" :label="__('Activer la génération automatique')"
                    :description="__('Désactivée, rien ne se génère tout seul : seuls vos lancements manuels comptent.')" />

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

                <x-ui::form-group :label="__('Brouillons générés en attente, au plus')" for="ai-pending-drafts-cap" :error="$errors->first('pendingDraftsCap')"
                    :hint="__('Tant que ce nombre de brouillons générés attend votre relecture, la génération automatique se met en pause. Vos lancements manuels ne sont pas concernés.')">
                    <x-ui::input type="number" min="1" max="50" id="ai-pending-drafts-cap" wire:model="pendingDraftsCap" class="sm:max-w-40" :error="$errors->has('pendingDraftsCap')" />
                </x-ui::form-group>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('Budget')">
            <div class="p-5">
                <x-ui::form-group :label="__('Plafond de dépense du mois ($)')" for="ai-spend-cap" :error="$errors->first('monthlySpendCap')"
                    :hint="__('En dollars, la monnaie de facturation du fournisseur d\'IA. Une fois ce montant dépensé dans le mois, plus rien ne se lance jusqu\'au mois suivant. Une génération commencée va au bout.')">
                    <x-ui::input type="number" step="0.01" min="0" id="ai-spend-cap" wire:model="monthlySpendCap" class="sm:max-w-40" :error="$errors->has('monthlySpendCap')" />
                </x-ui::form-group>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('Sujets')" :description="__('Ce que l\'IA doit privilégier, et ce qu\'elle doit laisser de côté. Un sujet par ligne.')">
            <div class="space-y-5 p-5">
                <x-ui::form-group :label="__('Sujets prioritaires et mots-clés')" for="ai-priority-topics" :error="$errors->first('priorityTopics')">
                    <x-ui::textarea id="ai-priority-topics" wire:model="priorityTopics" rows="4" :error="$errors->has('priorityTopics')" />
                </x-ui::form-group>

                <x-ui::form-group :label="__('Sujets à éviter')" for="ai-avoided-topics" :error="$errors->first('avoidedTopics')">
                    <x-ui::textarea id="ai-avoided-topics" wire:model="avoidedTopics" rows="3" :error="$errors->has('avoidedTopics')" />
                </x-ui::form-group>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('Modèle par étape')"
            :description="__('L\'identifiant du modèle utilisé à chaque étape, tel que le fournisseur l\'écrit. La liste propose les modèles connus ; tout autre identifiant est accepté.')">
            <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
                @foreach ($modelLabels as $step => $label)
                    <x-ui::form-group :label="$label" :for="'ai-model-'.$step" :error="$errors->first('models.'.$step)" wire:key="ai-model-{{ $step }}">
                        <x-ui::input :id="'ai-model-'.$step" wire:model="models.{{ $step }}" list="ai-known-models" autocomplete="off"
                            :error="$errors->has('models.'.$step)" />
                    </x-ui::form-group>
                @endforeach

                <datalist id="ai-known-models">
                    @foreach ($knownModels as $model)
                        <option value="{{ $model }}"></option>
                    @endforeach
                </datalist>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('Images')">
            <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
                <x-ui::form-group :label="__('Origine des images')" for="ai-image-mode" :error="$errors->first('imageMode')">
                    <x-ui::select id="ai-image-mode" wire:model="imageMode" :error="$errors->has('imageMode')"
                        :options="collect($imageModes)->mapWithKeys(fn ($mode) => [$mode->value => $mode->label()])->all()" />
                </x-ui::form-group>

                <x-ui::form-group :label="__('Images par article, au plus')" for="ai-max-images" :error="$errors->first('maxImages')"
                    :hint="__('L\'image principale comprise.')">
                    <x-ui::select id="ai-max-images" wire:model="maxImages" :error="$errors->has('maxImages')"
                        :options="collect($maxImagesChoices)->mapWithKeys(fn ($count) => [(string) $count => (string) $count])->all()" />
                </x-ui::form-group>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('E-mails')">
            <div class="p-5">
                <x-ui::form-group :label="__('Adresse qui reçoit les e-mails de la génération')" for="ai-notification-email" :error="$errors->first('notificationEmail')"
                    :hint="__('Un e-mail quand un lancement est terminé, et un autre si la génération est suspendue faute de budget.')">
                    <x-ui::input type="email" id="ai-notification-email" wire:model="notificationEmail" :error="$errors->has('notificationEmail')" />
                </x-ui::form-group>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('Mention de transparence')"
            :description="__('Une phrase affichée en bas des articles générés, dans chaque langue. Laissez vide pour ne rien afficher.')">
            <div class="space-y-5 p-5">
                @foreach ($transparencyNotice as $locale => $notice)
                    <x-ui::form-group :label="__('En :language', ['language' => $localeNames[$locale] ?? $locale])" :for="'ai-notice-'.$locale"
                        :error="$errors->first('transparencyNotice.'.$locale)" wire:key="ai-notice-{{ $locale }}">
                        <x-ui::textarea :id="'ai-notice-'.$locale" wire:model="transparencyNotice.{{ $locale }}" rows="2" maxlength="500"
                            :error="$errors->has('transparencyNotice.'.$locale)" />
                    </x-ui::form-group>
                @endforeach
            </div>
        </x-admin.panel>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[12px] leading-relaxed text-muted">{{ __('La clé du fournisseur d\'IA ne se règle pas ici : elle vit dans la configuration du site et n\'est jamais affichée.') }}</p>
            <x-ui::button type="submit" :loading="true" target="save" class="shrink-0">{{ __('Enregistrer les réglages') }}</x-ui::button>
        </div>
    </form>
</div>
