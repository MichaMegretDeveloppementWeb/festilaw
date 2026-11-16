{{-- En-tete commun aux trois pages de la generation par IA : le titre, le bouton de lancement (un composant a
     part, avec sa fenetre), puis les onglets. $active : 'overview', 'history' ou 'settings'. --}}
<x-ui::page-header :title="__('Génération IA')" :description="__('Des brouillons d\'articles proposés par l\'IA. Rien n\'est publié sans votre validation.')" class="mb-5">
    <livewire:admin.blog.generation-launcher />
</x-ui::page-header>

<x-admin.page-tabs class="mb-6" :tabs="[
    ['label' => __('Vue d\'ensemble'), 'href' => route('admin.blog.generation.index'), 'active' => $active === 'overview'],
    ['label' => __('Historique'), 'href' => route('admin.blog.generation.history'), 'active' => $active === 'history'],
    ['label' => __('Réglages'), 'href' => route('admin.blog.generation.settings'), 'active' => $active === 'settings'],
]" />
