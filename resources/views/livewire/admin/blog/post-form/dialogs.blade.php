{{-- Fenetres du formulaire d'article : lien du texte, descriptions d'une image, programmer, depublier,
     supprimer, ce qui reste a completer. --}}

{{-- Lien du texte. data-no-dirty : c'est l'editeur qui dit qu'il y a quelque chose a enregistrer. --}}
<x-ui::modal name="post-link" :title="__('Lien')">
    <div data-no-dirty>
        <x-ui::form-group :label="__('Adresse du lien')" for="link-href"
            :hint="__('Une adresse complète (https://…), une adresse du site (/fr/tarifs) ou mailto:… Vide : le lien est retiré.')">
            <x-ui::input id="link-href" type="text" x-model="linkForm.href" x-on:keydown.enter.prevent="applyLink()"
                placeholder="https://" autocomplete="off" autocapitalize="none" spellcheck="false" />
        </x-ui::form-group>
        <p x-show="linkForm.error" x-cloak x-text="linkForm.error" class="mt-1.5 text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
    </div>

    <x-slot:footer>
        <x-ui::button variant="ghost" class="mr-auto" x-on:click="removeLink()">{{ __('Retirer le lien') }}</x-ui::button>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'post-link')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="applyLink()">{{ __('Appliquer') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>

{{-- Descriptions d'une image, enregistrees tout de suite (sans attendre l'enregistrement de l'article). --}}
<x-ui::modal name="image-alt" :title="__('Descriptions de l\'image')">
    <div data-no-dirty class="space-y-4">
        <p class="text-[12px] leading-relaxed text-muted">{{ __('Une phrase qui dit ce que montre l\'image, pour les personnes qui ne la voient pas et pour Google. Une par langue ; elle est enregistrée tout de suite.') }}</p>
        @foreach ($locales as $locale => $language)
            <x-ui::form-group :label="ucfirst($language)" for="image-alt-{{ $locale }}">
                <x-ui::textarea id="image-alt-{{ $locale }}" rows="2" maxlength="300" x-model="altForm.{{ $locale }}" lang="{{ $locale }}" />
            </x-ui::form-group>
        @endforeach
    </div>

    <x-slot:footer>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'image-alt')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="saveAlt()" x-bind:disabled="altForm.saving" :loading="true" target="saveImageAlt">{{ __('Enregistrer les descriptions') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>

@unless ($isPublished)
    <x-ui::modal name="schedule-post" :title="__('Programmer la publication')">
        <x-ui::form-group :label="__('Date et heure de publication (UTC)')" for="scheduled-for"
            :hint="__('Heure UTC : en France, ajoutez 1 heure en hiver et 2 heures en été.')" :error="$errors->first('scheduledFor')">
            <x-ui::input type="datetime-local" id="scheduled-for" wire:model="scheduledFor" :error="$errors->has('scheduledFor')" />
        </x-ui::form-group>
        <p class="mt-4 text-[12px] leading-relaxed text-muted">{{ __('L\'article est enregistré maintenant, puis mis en ligne tout seul à cette date. S\'il est incomplet, il reste en brouillon et l\'écran dit ce qui manque.') }}</p>

        <x-slot:footer>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'schedule-post')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button x-on:click="schedule()" x-bind:disabled="busy" :loading="true" target="schedule">{{ __('Programmer') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endunless

@if ($postId !== null && ($isPublished || $isScheduled))
    <x-ui::modal name="unpublish-post" variant="confirm" :title="$isPublished ? __('Dépublier cet article ?') : __('Annuler la programmation ?')">
        @if ($isPublished)
            <p>{{ __('Il ne sera plus visible sur le site et repassera en brouillon. Vous pourrez le publier de nouveau, à la même adresse.') }}</p>
        @else
            <p>{{ __('L\'article repassera en brouillon et ne sera pas publié à la date prévue.') }}</p>
        @endif

        <x-slot:actions>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'unpublish-post')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button x-on:click="$wire.unpublish(); $dispatch('ui-close-modal', 'unpublish-post')">{{ $isPublished ? __('Dépublier') : __('Annuler la programmation') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
@endif

@if ($postId !== null)
    <x-ui::modal name="delete-post" variant="confirm" :title="__('Supprimer cet article ?')">
        @if ($isPublished)
            <p>{{ __('Il est publié : ses adresses répondront ensuite que la page n\'existe plus.') }}</p>
        @endif
        <p>{{ __('La suppression est définitive, dans les trois langues.') }}</p>

        <x-slot:actions>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'delete-post')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button variant="danger" x-on:click="dirty = false; $wire.delete(); $dispatch('ui-close-modal', 'delete-post')">{{ __('Supprimer') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
@endif

{{-- Jamais sur un article publie et complet : « pret a publier » n'y a pas de sens. --}}
@if ($view !== null && ($blockerCount > 0 || (! $isPublished && $warningCount > 0)))
    <x-ui::modal name="post-readiness" :title="$view->isReady() ? __('Prêt à publier') : __('À compléter avant de publier')">
        <p class="text-[12px] text-muted">{{ __('D\'après le dernier enregistrement.') }}</p>

        @if ($view->blockers !== [])
            <div class="mt-4">
                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">{{ __('Il manque pour publier') }}</p>
                <ul class="list-disc space-y-0.5 pl-5 text-[13px] text-primary">
                    @foreach ($view->blockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="mt-4 flex items-center gap-2 text-[13px] font-medium text-emerald-700 dark:text-emerald-400">
                <x-ui::icon name="check-circle" /> {{ __('Rien ne bloque la publication.') }}
            </p>
        @endif

        @if ($view->warnings !== [])
            <div class="mt-4">
                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('Conseillé, sans être obligatoire') }}</p>
                <ul class="list-disc space-y-0.5 pl-5 text-[13px] text-secondary">
                    @foreach ($view->warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-slot:footer>
            <x-ui::button variant="secondary" x-on:click="$dispatch('ui-close-modal', 'post-readiness')">{{ __('Fermer') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
