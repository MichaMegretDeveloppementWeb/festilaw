{{-- Emplacement d'une image de l'article dans le panneau des reglages.
     $usage : 'featured' ou 'share' ; $property : la propriete du composant qui porte son identifiant ;
     $label, $help ; $compact : l'emplacement vide tient sur une ligne (image facultative) ; $missing : l'image
     manque pour publier (point ambre du libelle).
     L'apercu vient de la liste d'images que tient le navigateur (`images`) : une image qui vient d'etre
     envoyee s'affiche avant meme l'enregistrement de l'article.
     Ses boutons font 36 px de haut, comme « Tout traduire depuis… » : a cet endroit, la hauteur du kit (44 px)
     les faisait passer avant l'image (Micha, 24/11/2026).
     L'image principale a en plus « Image automatique » (banque d'images ou IA, 24/11/2026) : pendant la demande,
     les autres boutons de l'emplacement attendent. --}}
<div>
    <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300" @if ($missing ?? false) data-missing="{{ $usage }}_image" @endif>{{ $label }}</p>

    <template x-if="images[$wire.{{ $property }}]">
        <div>
            <img x-bind:src="images[$wire.{{ $property }}].url" x-bind:alt="images[$wire.{{ $property }}].alt.fr" class="aspect-video w-full rounded-lg bg-elevated object-cover">
            <p class="mt-1.5 text-[12px]"
                x-bind:class="['en', 'fr', 'es'].every((locale) => images[$wire.{{ $property }}].alt[locale]) ? 'text-muted' : 'text-amber-700 dark:text-amber-400'"
                x-text="['en', 'fr', 'es'].every((locale) => images[$wire.{{ $property }}].alt[locale]) ? @js(__('Décrite dans les trois langues.')) : @js(__('Description à compléter.'))"></p>
            <div class="mt-2 flex flex-wrap gap-2">
                <x-ui::button variant="secondary" size="compact" class="min-h-9" x-on:click="pickImage('{{ $usage }}')" x-bind:disabled="uploading !== null || imageSearch.pending !== null">{{ __('Remplacer') }}</x-ui::button>
                <x-ui::button variant="secondary" size="compact" class="min-h-9" x-on:click="describeImage($wire.{{ $property }})">{{ __('Descriptions') }}</x-ui::button>
                <x-ui::button variant="ghost" size="compact" class="min-h-9" x-on:click="removeImage('{{ $usage }}')" x-bind:disabled="imageSearch.pending !== null">{{ __('Retirer') }}</x-ui::button>
            </div>
        </div>
    </template>

    <template x-if="! images[$wire.{{ $property }}]">
        <button type="button" x-on:click="pickImage('{{ $usage }}')" x-bind:disabled="uploading !== null || imageSearch.pending !== null"
            @class([
                'flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-default text-muted transition-colors hover:border-gray-300 hover:text-secondary disabled:cursor-not-allowed disabled:opacity-50 dark:hover:border-gray-600',
                'aspect-video flex-col' => ! ($compact ?? false),
                'py-3' => $compact ?? false,
            ])>
            <x-ui::icon name="photo" @class(['h-8 w-8' => ! ($compact ?? false), 'h-5 w-5' => $compact ?? false]) stroke-width="1" />
            <span class="text-[13px] font-medium">{{ __('Choisir une image') }}</span>
        </button>
    </template>

    @if ($usage === 'featured')
        @include('livewire.admin.blog.post-form.find-image')
    @endif

    <p x-show="uploading === '{{ $usage }}'" x-cloak class="mt-1.5 flex items-center gap-2 text-[12px] text-muted" role="status">
        <x-ui::spinner size="xs" /> {{ __('Envoi et préparation de l\'image…') }}
    </p>
    <p class="mt-1.5 text-[12px] leading-relaxed text-muted">{{ $help }}</p>
</div>
