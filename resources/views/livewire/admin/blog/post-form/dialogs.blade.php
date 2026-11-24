{{-- Fenetres du formulaire d'article : lien du texte, source, articles a lier, descriptions d'une image,
     programmer, depublier, supprimer, ce qui reste a completer. --}}

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

{{-- Source de l'article, nouvelle ou modifiee. Les deux champs vivent dans le navigateur (sourceForm) ; le
     serveur les controle a la validation, et la source n'entre dans la liste que s'il les accepte. --}}
<x-ui::modal name="post-source" :title="__('Source de l\'article')">
    <div data-no-dirty class="space-y-4">
        <div>
            <x-ui::form-group :label="__('Intitulé')" for="source-label">
                <x-ui::input id="source-label" type="text" x-model="sourceForm.label" x-on:keydown.enter.prevent="saveSource()"
                    maxlength="200" :placeholder="__('Par exemple : Règlement (UE) 2023/988')" autocomplete="off"
                    x-bind:aria-invalid="sourceForm.errors.label ? 'true' : 'false'" />
            </x-ui::form-group>
            <p x-show="sourceForm.errors.label" x-cloak x-text="sourceForm.errors.label" class="mt-1.5 text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
        </div>

        <div>
            <x-ui::form-group :label="__('Lien')" for="source-url">
                <x-ui::input id="source-url" type="text" inputmode="url" x-model="sourceForm.url" x-on:keydown.enter.prevent="saveSource()"
                    placeholder="https://" autocomplete="off" autocapitalize="none" spellcheck="false"
                    x-bind:aria-invalid="sourceForm.errors.url ? 'true' : 'false'" />
            </x-ui::form-group>
            <p x-show="sourceForm.errors.url" x-cloak x-text="sourceForm.errors.url" class="mt-1.5 text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
        </div>

        <p x-show="sourceForm.errors.form" x-cloak x-text="sourceForm.errors.form" class="text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
    </div>

    <x-slot:footer>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'post-source')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="saveSource()" x-bind:disabled="sourceForm.saving" data-save-source>
            <span x-text="sourceForm.index === null ? @js(__('Ajouter la source')) : @js(__('Enregistrer la source'))">{{ __('Ajouter la source') }}</span>
        </x-ui::button>
    </x-slot:footer>
</x-ui::modal>

{{-- Nouvelle categorie, ouverte depuis la liste des categories de l'article. Le nom est demande dans les trois
     langues, car la categorie a une page publique dans chacune ; le reste se complete plus tard depuis la page
     Categories. Les champs vivent dans le navigateur (categoryForm), le serveur rend ses refus par champ. --}}
<x-ui::modal name="post-category" :title="__('Nouvelle catégorie')">
    <div data-no-dirty class="space-y-4">
        @foreach ($locales as $locale => $language)
            <div wire:key="category-name-{{ $locale }}">
                <x-ui::form-group :label="__('Nom en :language', ['language' => $language])" :for="'category-name-'.$locale">
                    <x-ui::input :id="'category-name-'.$locale" type="text" x-model="categoryForm.names.{{ $locale }}" x-on:keydown.enter.prevent="createCategory()"
                        maxlength="255" autocomplete="off" x-bind:aria-invalid="categoryForm.errors['{{ $locale }}'] ? 'true' : 'false'" />
                </x-ui::form-group>
                <p x-show="categoryForm.errors['{{ $locale }}']" x-cloak x-text="categoryForm.errors['{{ $locale }}']" class="mt-1.5 text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
            </div>
        @endforeach

        <p class="text-[12px] text-muted">{{ __('Vous pourrez compléter sa description plus tard, depuis la page Catégories.') }}</p>
        <p x-show="categoryForm.errors.form" x-cloak x-text="categoryForm.errors.form" class="text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
    </div>

    <x-slot:footer>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'post-category')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="createCategory()" x-bind:disabled="categoryForm.saving" data-confirm-category>{{ __('Créer la catégorie') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>

{{-- Articles lies. En haut, ceux deja choisis, en pastilles courtes qu'une croix retire : Micha devait fermer la
     fenetre pour les voir (19/11). Dessous, les articles a lier : la liste n'est lue par le serveur que pendant
     que la fenetre est ouverte ($pickingRelated), et la recherche filtre dans le navigateur. Un clic ajoute
     l'article, la fenetre reste ouverte pour en ajouter d'autres. Ajouter ou retirer agit aussitot sur la
     section « Articles liés » de l'article, qui s'enregistre avec lui. --}}
<x-ui::modal name="post-related" :title="__('Articles liés')">
    <div data-no-dirty>
        @if ($related !== [])
            <ul class="mb-4 flex flex-wrap gap-1.5" data-related-chosen>
                @foreach ($related as $item)
                    <li wire:key="related-chosen-{{ $item['id'] }}"
                        class="inline-flex max-w-[14rem] items-center gap-1 rounded-full bg-elevated py-0.5 pl-2.5 pr-1 text-[12px] font-medium text-primary">
                        <span @class(['truncate', 'italic text-muted' => ! $item['title']]) title="{{ $item['title'] }}">{{ $item['title'] ?? __('Sans titre') }}</span>
                        <button type="button" x-on:click="dirty = true; $wire.removeRelated({{ $item['id'] }})"
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-muted transition-colors hover:bg-gray-200 hover:text-primary dark:hover:bg-gray-700"
                            aria-label="{{ __('Retirer :title des articles liés', ['title' => $item['title'] ?? __('cet article')]) }}" title="{{ __('Retirer') }}">
                            <x-ui::icon name="x-mark" class="h-3 w-3" stroke-width="2" />
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! $pickingRelated)
            <div class="flex justify-center py-8"><x-ui::spinner size="lg" /></div>
        @elseif (count($related) >= $relatedLimit)
            <p class="text-[13px] text-secondary">{{ __('Un article garde :count articles liés au plus.', ['count' => $relatedLimit]) }}</p>
        @elseif ($relatedCandidates === [])
            <p class="text-[13px] text-secondary">{{ __('Aucun autre article à proposer.') }}</p>
        @else
            <x-ui::search-input id="related-search" x-model="relatedQuery" :placeholder="__('Rechercher un titre')"
                aria-label="{{ __('Rechercher un article à lier') }}" />

            <x-ui::scroll-area maxHeight="20rem" class="mt-3">
                <ul data-related-candidates>
                    @foreach ($relatedCandidates as $id => $label)
                        <li wire:key="related-candidate-{{ $id }}" x-show="matchesQuery(@js($label), relatedQuery)">
                            <button type="button" x-on:click="dirty = true; $wire.addRelated({{ $id }})"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-[13px] text-primary transition-colors hover:bg-elevated">
                                <span class="min-w-0 truncate">{{ $label }}</span>
                                <span class="flex shrink-0 items-center gap-1 text-[12px] font-medium text-secondary"><x-ui::icon name="plus" class="h-3.5 w-3.5" /> {{ __('Ajouter') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-ui::scroll-area>

            @if (count($relatedCandidates) >= $relatedCandidateLimit)
                <p class="mt-2 text-[12px] text-muted">{{ __('Seuls les :count articles les plus récents sont proposés.', ['count' => $relatedCandidateLimit]) }}</p>
            @endif
        @endif
    </div>

    <x-slot:footer>
        <span class="mr-auto text-[12px] tabular-nums text-muted">{{ trans_choice('{0} Aucun article lié|{1} :count article lié sur :limit|[2,*] :count articles liés sur :limit', count($related), ['count' => count($related), 'limit' => $relatedLimit]) }}</span>
        <x-ui::button x-on:click="$dispatch('ui-close-modal', 'post-related')">{{ __('Terminé') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>

{{-- Image dans le texte. On choisit un fichier (ou on le depose), il s'affiche dans l'outil de recadrage sans
     attendre l'envoi, puis on choisit sa largeur dans l'article et sa description. A la validation, le fichier
     part avec son cadrage et c'est le serveur qui decoupe. La meme fenetre rouvre une image deja placee pour
     lui donner un autre cadrage (imageForm.mode === 'recrop').
     L'outil de recadrage ecrit dans son cadre : Livewire n'y touche pas (wire:ignore). --}}
<x-ui::modal name="post-image" width="48rem" :title="__('Image dans le texte')">
    <div data-no-dirty class="space-y-4">
        <div x-show="! imageForm.preview">
            <label for="post-image-file" x-on:dragover.prevent x-on:drop.prevent="dropImageFile($event)"
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-default px-6 py-12 text-center text-muted transition-colors hover:border-gray-300 hover:text-secondary dark:hover:border-gray-600">
                <x-ui::icon name="photo" class="h-8 w-8" stroke-width="1" />
                <span class="text-[13px] font-medium text-secondary">{{ __('Déposez une image ici, ou cliquez pour la choisir') }}</span>
                <span class="text-[12px]">{{ __('JPEG, PNG ou WebP, :megabytes Mo au plus.', ['megabytes' => (int) floor(config('blog.images.max_upload_kilobytes') / 1024)]) }}</span>
            </label>
            <input id="post-image-file" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" x-on:change="chooseImageFile($event)">
        </div>

        <div x-show="imageForm.preview" x-cloak class="space-y-4">
            <div data-image-crop-area class="relative h-64 overflow-hidden rounded-lg bg-elevated sm:h-[22rem]" wire:ignore>
                {{-- Hors du flux : une grande photo posee dans le flux donnerait sa largeur a la fenetre, qui
                     deborderait d'un ecran de telephone au moment ou l'outil mesure sa zone. --}}
                <img id="post-image-canvas" alt="" class="absolute inset-0 block h-full w-full object-contain">
            </div>
            <p x-show="! imageForm.ready && ! imageForm.error" class="flex items-center gap-2 text-[12px] text-muted" role="status">
                <x-ui::spinner size="xs" /> {{ __('Chargement de l\'image…') }}
            </p>

            <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
                <div>
                    <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Format') }}</p>
                    <x-ui::segmented :legend="__('Format du cadrage')" x-model="imageForm.ratio" x-on:change="setImageRatio()"
                        :options="['free' => __('Libre'), 'landscape' => '16:9', 'standard' => '4:3', 'square' => __('Carré')]" />
                </div>

                <div class="min-w-44 flex-1">
                    <label for="post-image-zoom" class="mb-1.5 block text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Zoom') }}</label>
                    <input id="post-image-zoom" type="range" min="0" max="100" step="1" x-model.number="imageForm.zoom" x-on:input="zoomImage()"
                        class="block h-8 w-full accent-gray-900 dark:accent-white">
                </div>
            </div>
            <p class="text-[12px] leading-relaxed text-muted">{{ __('Étirez le cadre par ses poignées pour choisir la partie gardée. Faites glisser l\'image pour la déplacer sous le cadre.') }}</p>

            <div x-show="imageForm.mode === 'new'" class="space-y-4 border-t border-subtle pt-4">
                <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
                    <div>
                        <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Largeur dans l\'article') }}</p>
                        <x-ui::segmented :legend="__('Largeur de l\'image dans l\'article')" x-model="imageForm.width"
                            :options="['full' => __('Pleine'), 'medium' => __('Moyenne'), 'small' => __('Petite')]" />
                    </div>

                    {{-- La position n'existe que pour une image reduite : en pleine largeur, il n'y a rien a cote. --}}
                    <div x-show="imageForm.width !== 'full'" x-cloak>
                        <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300">{{ __('Position') }}</p>
                        <x-ui::segmented :legend="__('Position de l\'image dans l\'article')" x-model="imageForm.align"
                            :options="['left' => __('Gauche'), 'center' => __('Centre'), 'right' => __('Droite')]" />
                    </div>
                </div>
                <p x-show="imageForm.width !== 'full'" x-cloak class="-mt-1 text-[12px] leading-relaxed text-muted">{{ __('À gauche ou à droite, l\'image a sa propre zone de texte à côté d\'elle. Sur un écran étroit, elle passe au-dessus de ce texte.') }}</p>

                <x-ui::form-group :label="__('Description de l\'image')" for="post-image-alt"
                    :hint="__('Une phrase qui dit ce que montre l\'image, pour les personnes qui ne la voient pas et pour Google.')">
                    <x-ui::input id="post-image-alt" type="text" x-model="imageForm.alt" maxlength="300" autocomplete="off" />
                </x-ui::form-group>
            </div>
        </div>

        <p x-show="imageForm.error" x-cloak x-text="imageForm.error" class="text-[12px] text-red-700 dark:text-red-400" role="alert"></p>
    </div>

    <x-slot:footer>
        <x-ui::button variant="ghost" class="mr-auto" x-show="imageForm.preview && imageForm.mode === 'new'" x-on:click="resetImageFile()">{{ __('Changer d\'image') }}</x-ui::button>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'post-image')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="submitImage()" x-bind:disabled="! imageForm.ready || imageForm.busy" data-submit-image>
            <span x-text="imageForm.busy ? @js(__('Envoi en cours…')) : (imageForm.mode === 'new' ? @js(__('Insérer l\'image')) : @js(__('Appliquer le cadrage')))">{{ __('Insérer l\'image') }}</span>
        </x-ui::button>
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
            <p>{{ __('Les liens vers cet article dans les autres articles seront retirés.') }}</p>
        @else
            <p>{{ __('L\'article repassera en brouillon et ne sera pas publié à la date prévue.') }}</p>
        @endif

        <x-slot:actions>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'unpublish-post')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button x-on:click="$wire.unpublish(); $dispatch('ui-close-modal', 'unpublish-post')">{{ $isPublished ? __('Dépublier') : __('Annuler la programmation') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
@endif

{{-- Traduction assistee : la langue visee a deja du texte. --}}
<x-ui::modal name="translate-post" variant="confirm" :title="__('Remplacer le texte de cette langue ?')">
    <p>{{ __('La traduction remplacera le titre, l\'extrait et le texte déjà saisis dans cette langue. Rien n\'est enregistré tant que vous n\'enregistrez pas l\'article.') }}</p>

    <x-slot:actions>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'translate-post')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="requestTranslation()" data-confirm-translation>{{ __('Traduire') }}</x-ui::button>
    </x-slot:actions>
</x-ui::modal>

{{-- Traduction d'un seul champ qui a deja du texte (demande de Micha du 24/11/2026). --}}
<x-ui::modal name="translate-field" variant="confirm" :title="__('Remplacer ce champ ?')">
    <p>{{ __("La traduction remplacera le texte déjà saisi dans ce champ, dans cette langue. Rien n'est enregistré tant que vous n'enregistrez pas l'article.") }}</p>

    <x-slot:actions>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'translate-field')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="requestTranslation()" data-confirm-field-translation>{{ __('Traduire') }}</x-ui::button>
    </x-slot:actions>
</x-ui::modal>

{{-- Image principale trouvee ou creee depuis l'article alors qu'il en a deja une (demande de Micha du 24/11/2026) :
     la nouvelle se montre avant de remplacer l'actuelle. Une image ecartee reste dans « Images déjà obtenues ». --}}
<x-ui::modal name="use-found-image" :title="__('Utiliser cette image ?')">
    <template x-if="imageSearch.candidate">
        <div>
            <img x-bind:src="imageSearch.candidate.url" x-bind:alt="imageSearch.candidate.alt.fr" class="aspect-video w-full rounded-lg bg-elevated object-cover">
            <p class="mt-2 text-[13px] text-secondary" x-text="imageSearch.candidate.alt.fr"></p>
        </div>
    </template>
    <p class="mt-3 text-[13px] text-muted">{{ __("Elle remplacera l'image principale actuelle. Rien n'est enregistré tant que vous n'enregistrez pas l'article.") }}</p>

    <x-slot:footer>
        <x-ui::button variant="ghost" x-on:click="keepCurrentImage()">{{ __("Garder l'actuelle") }}</x-ui::button>
        <x-ui::button x-on:click="useCandidateImage()" data-use-found-image>{{ __('Utiliser cette image') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>

{{-- Images deja obtenues pour l'article (Micha, 24/11/2026) : l'image principale de sa generation et celles
     d'« Image automatique », meme remplacees, la plus recente d'abord. Un clic choisit une vignette, « Utiliser cette
     image » la pose. Rien n'est enregistre avant l'article. --}}
<x-ui::modal name="obtained-images" :title="__('Images déjà obtenues')">
    <p class="text-[13px] text-muted">{{ __("Les images trouvées ou générées pour cet article. Rien n'est enregistré tant que vous n'enregistrez pas l'article.") }}</p>
    <ul class="mt-4 grid grid-cols-2 gap-3" data-obtained-images>
        <template x-for="obtained in imageSearch.obtained" x-bind:key="obtained.id">
            <li>
                <button type="button" x-on:click="imageSearch.picked = obtained.id" x-bind:aria-pressed="(imageSearch.picked === obtained.id).toString()"
                    x-bind:data-obtained-image="obtained.id"
                    class="block w-full rounded-lg p-1 text-left transition-shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
                    x-bind:class="imageSearch.picked === obtained.id ? 'ring-2 ring-gray-900 dark:ring-white' : 'hover:ring-1 hover:ring-gray-300 dark:hover:ring-gray-600'">
                    <span class="relative block">
                        <img x-bind:src="images[obtained.id]?.url" x-bind:alt="images[obtained.id]?.alt?.fr ?? ''" class="aspect-video w-full rounded-md bg-elevated object-cover">
                        <span x-show="obtained.id === $wire.featuredImageId" class="absolute left-1.5 top-1.5"><x-ui::badge>{{ __('Actuelle') }}</x-ui::badge></span>
                    </span>
                    <span class="mt-1.5 block px-0.5 text-[12px] text-secondary" x-text="obtained.origin + ', ' + obtained.date"></span>
                </button>
            </li>
        </template>
    </ul>

    <x-slot:footer>
        <x-ui::button variant="ghost" x-on:click="$dispatch('ui-close-modal', 'obtained-images')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button x-on:click="useObtainedImage()" x-bind:disabled="imageSearch.picked === null || imageSearch.picked === $wire.featuredImageId" data-use-obtained-image>{{ __('Utiliser cette image') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>

@if ($postId !== null)
    <x-ui::modal name="delete-post" variant="confirm" :title="__('Supprimer cet article ?')">
        @if ($isPublished)
            <p>{{ __('Il est publié : ses adresses répondront ensuite que la page n\'existe plus.') }}</p>
        @endif
        <p>{{ __('La suppression est définitive, dans les trois langues.') }}</p>
        <p>{{ __('Les liens vers cet article dans les autres articles seront retirés.') }}</p>

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
