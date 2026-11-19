{{-- Barre d'actions d'un formulaire long : elle reste en bas de l'ecran pendant qu'on fait defiler, a la
     largeur du contenu. Une bordure de la couleur des filets la dessine, sans ombre ni coins arrondis (decision
     de Micha du 19/11) : elle se lit comme les autres traits de la page et s'aligne sur eux.
     Le cadre exterieur colle au bas de l'ecran et porte le fond de la page : rien ne depasse sous la barre
     pendant le defilement. Sa marge negative reprend sa propre marge interieure, pour que le bas de page
     garde son espace habituel. Le parent est une colonne flexible haute comme l'ecran : `mt-auto` tient la
     barre en bas meme quand le formulaire est court. --}}
<div class="sticky bottom-0 z-30 -mb-3 mt-auto bg-page pb-3 group-data-[surface=plain]/surface:bg-surface sm:-mb-4 sm:pb-4">
    <div data-action-bar {{ $attributes->merge(['class' => 'border border-default bg-surface px-4 py-3 sm:px-5']) }}>
        {{ $slot }}
    </div>
</div>
