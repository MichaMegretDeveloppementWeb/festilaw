{{-- « Image automatique » sous l'image principale (demande de Micha du 24/11/2026) : une photo libre choisie par
     l'IA dans une banque d'images, ou une image generee par l'IA avec le modele des reglages, que l'image manque ou
     qu'elle ne convienne pas. La demande part en arriere-plan (PostFeaturedImage) ; le bouton dit ce qui est en
     cours, et la fenetre « Utiliser cette image ? » (dialogs.blade.php) s'ouvre quand l'article a deja une image.
     Le bouton est a gauche, avec une zone de clic plus large que celle des boutons de traduction, sans marge
     negative : la colonne qui defile coupe ce qui depasse, et le survol perdait son bord gauche. Le menu reprend
     les termes de l'origine des images des reglages, et chaque choix dit en une ligne qui choisit ou genere
     l'image (retours de Micha du 24/11/2026). --}}
<div class="mt-2" data-find-image>
    <x-ui::dropdown width="w-72">
        <x-slot:trigger>
            <button type="button" aria-haspopup="menu" x-bind:disabled="imageSearch.pending !== null || uploading !== null"
                class="inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 text-[13px] font-medium text-secondary transition-colors hover:bg-elevated hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                <template x-if="imageSearch.pending === null">
                    <span class="inline-flex items-center gap-1.5"><x-ui::icon name="sparkles" class="ai-icon h-[18px] w-[18px]" /> {{ __('Image automatique') }}</span>
                </template>
                <template x-if="imageSearch.pending === 'stock'">
                    <span class="inline-flex items-center gap-1.5"><x-ui::spinner size="sm" /> {{ __('Choix de la photo en cours…') }}</span>
                </template>
                <template x-if="imageSearch.pending === 'generated'">
                    <span class="inline-flex items-center gap-1.5"><x-ui::spinner size="sm" /> {{ __('Génération de l\'image en cours…') }}</span>
                </template>
            </button>
        </x-slot:trigger>
        <x-ui::dropdown-item x-on:click="askImage('stock')" data-find-image-mode="stock">
            <span class="block py-0.5 text-left">
                <span class="block font-medium text-primary">{{ __('Banque d\'images') }}</span>
                <span class="mt-0.5 block text-[12px] text-muted">{{ __('Choisie par l\'IA dans une banque d\'images') }}</span>
            </span>
        </x-ui::dropdown-item>
        <x-ui::dropdown-item x-on:click="askImage('generated')" data-find-image-mode="generated">
            <span class="block py-0.5 text-left">
                <span class="block font-medium text-primary">{{ __('Générée par IA') }}</span>
                <span class="mt-0.5 block text-[12px] text-muted">{{ __('L\'IA génère une image en rapport avec le thème de l\'article') }}</span>
            </span>
        </x-ui::dropdown-item>
        {{-- Les images deja obtenues pour cet article, meme remplacees (Micha, 24/11/2026) : seulement s'il y en a. --}}
        <div x-show="imageSearch.obtained.length > 0" x-cloak>
            <x-ui::dropdown-item separator />
            <x-ui::dropdown-item x-on:click="openObtainedImages()" data-obtained-images-open>
                <span class="block py-0.5 text-left">
                    <span class="block font-medium text-primary">{{ __('Images déjà obtenues') }} (<span x-text="imageSearch.obtained.length"></span>)</span>
                    <span class="mt-0.5 block text-[12px] text-muted">{{ __('Reprendre une image trouvée ou générée pour cet article') }}</span>
                </span>
            </x-ui::dropdown-item>
        </div>
    </x-ui::dropdown>
</div>
