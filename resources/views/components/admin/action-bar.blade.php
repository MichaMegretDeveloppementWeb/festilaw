{{-- Barre d'actions d'un formulaire long : elle reste en bas de l'ecran pendant qu'on fait defiler, a la
     largeur du contenu, portee par une ombre (pas de bordure).
     Le cadre exterieur colle au bas de l'ecran et porte le fond de la page : rien ne depasse sous la barre
     pendant le defilement. Sa marge negative reprend sa propre marge interieure, pour que le bas de page
     garde son espace habituel. Le parent est une colonne flexible haute comme l'ecran : `mt-auto` tient la
     barre en bas meme quand le formulaire est court. --}}
<div class="sticky bottom-0 z-30 -mb-3 mt-auto bg-page pb-3 group-data-[surface=plain]/surface:bg-surface sm:-mb-4 sm:pb-4">
    <div data-action-bar {{ $attributes->merge(['class' => 'rounded-lg bg-surface px-4 py-3 shadow-[0_6px_28px_-6px_rgb(0_0_0/0.22)] sm:px-5 dark:shadow-[0_6px_28px_-6px_rgb(0_0_0/0.7)] dark:ring-1 dark:ring-white/10']) }}>
        {{ $slot }}
    </div>
</div>
