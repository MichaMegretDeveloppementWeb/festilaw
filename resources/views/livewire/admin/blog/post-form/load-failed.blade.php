{{-- L'article n'a pas pu etre lu a l'ouverture (HandlesMountFailure) : le formulaire n'est pas montre, car vide il
     enregistrerait un nouvel article. Le bouton du bloc recharge la page. --}}
<div>
    <a href="{{ route('admin.blog.posts.index') }}" class="inline-flex items-center gap-1.5 text-[13px] text-muted transition-colors hover:text-primary">
        <x-ui::icon name="arrow-left" class="h-3.5 w-3.5" /> {{ __('Articles') }}
    </a>

    <x-ui::page-header :title="__('Modifier l\'article')" class="mb-8 mt-2" />

    <x-ui::load-failure :title="__('Le chargement de l\'article a échoué.')"
        :description="__('Réessayez. Si le problème persiste, contactez l\'assistance technique.')" retry="reloadPage" />
</div>
