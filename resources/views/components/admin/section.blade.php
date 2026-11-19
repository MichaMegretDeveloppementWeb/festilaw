@props(['title', 'description' => null, 'spaced' => true])
{{-- Une section d'un ecran sur page blanche : un titre de 18 px, une phrase facultative, un filet, puis le
     contenu. Tous les ecrans du back-office l'emploient depuis le 16/11/2026, a la place des cartes a bandeau. $actions (facultatif) : ce qui se range a droite du titre (un lien, un compte).
     La section porte elle-meme son ecart de 64 px avec ce qui la precede : Micha a demande le 19/11 des sections
     nettement separees sur toutes les pages, et l'ecart se regle ainsi a un seul endroit. Premiere de son
     conteneur, elle n'en prend pas. $spaced = false quand elle suit directement le titre de la page ou qu'elle se
     place dans une grille, ou cet ecart la decalerait.
     Le titre, le filet et le contenu partent du meme bord que le titre de la page, comme la barre d'actions
     (decision de Micha du 19/11 : les filets qui depassaient creaient un decalage). --}}
<section {{ $attributes->class(['not-first:mt-16' => $spaced]) }}>
    <div class="flex items-end justify-between gap-4 border-b border-default pb-3">
        <div class="min-w-0">
            <h2 class="text-[18px] font-semibold leading-snug tracking-tight text-primary">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-[13px] leading-relaxed text-muted">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="shrink-0 text-[13px] text-muted">{{ $actions }}</div>
        @endisset
    </div>

    {{ $slot }}
</section>
