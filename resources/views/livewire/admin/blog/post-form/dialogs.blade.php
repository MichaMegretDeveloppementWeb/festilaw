{{-- Fenetres du formulaire d'article : programmer, depublier, supprimer, ce qui reste a completer. --}}

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
