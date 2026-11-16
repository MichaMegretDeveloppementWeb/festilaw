{{-- En-tete des pages de la generation par IA. Aucun onglet (decision de Micha du 16/11/2026) : la page
     d'arrivee porte le titre de la rubrique, une roue crantee qui mene aux reglages et le bouton de lancement ;
     l'historique et les reglages sont deux pages a part, avec un lien de retour au-dessus de leur titre.
     $page : 'index', 'history' ou 'settings'. --}}
@if ($page === 'index')
    <x-ui::page-header :title="__('Génération IA')" :description="__('Des brouillons d\'articles proposés par l\'IA. Rien n\'est publié sans votre validation.')" class="mb-6">
        <x-ui::button variant="secondary" :href="route('admin.blog.generation.settings')" data-open-settings
            :aria-label="__('Réglages de la génération IA')" :title="__('Réglages')">
            <x-ui::icon name="cog-6-tooth" class="h-[18px] w-[18px]" />
        </x-ui::button>

        <livewire:admin.blog.generation-launcher />
    </x-ui::page-header>
@else
    <a href="{{ route('admin.blog.generation.index') }}" data-back-to-generation
        class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Génération IA') }}
    </a>

    @if ($page === 'history')
        <x-ui::page-header :title="__('Historique des générations')" :description="__('Toutes les générations, de la plus récente à la plus ancienne.')" class="mb-6 mt-2">
            <livewire:admin.blog.generation-launcher />
        </x-ui::page-header>
    @else
        <x-ui::page-header :title="__('Réglages de la génération IA')" :description="__('Rythme, images, budget, sujets et modèles des articles proposés par l\'IA.')" class="mb-6 mt-2" />
    @endif
@endif
