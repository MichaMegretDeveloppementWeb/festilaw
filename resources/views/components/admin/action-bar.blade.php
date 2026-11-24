{{-- Barre d'actions d'un formulaire long : elle reste en bas de l'ecran pendant qu'on fait defiler, a la
     largeur du contenu. Une bordure de la couleur des filets la dessine, sans ombre ni coins arrondis (decision
     de Micha du 19/11) : elle se lit comme les autres traits de la page et s'aligne sur eux.
     Collee, elle touche le bas de l'ecran, sans espace dessous ; tout en bas de la page, elle reprend sa place
     et la marge du bas de page la decolle (demande de Micha du 24/11). Le parent est une colonne flexible haute
     comme l'ecran : `mt-auto` tient la barre en bas meme quand le formulaire est court. --}}
<div class="sticky bottom-0 z-30 mt-auto">
    <div data-action-bar {{ $attributes->merge(['class' => 'border border-default bg-surface px-4 py-3 sm:px-5']) }}>
        {{ $slot }}
    </div>
</div>
