@props(['title', 'description' => null])
{{-- Une section d'un ecran sur page blanche : un titre de 16 px, une phrase facultative, un filet, puis le
     contenu. Tous les ecrans du back-office l'emploient depuis le 16/11/2026, a la place des cartes a bandeau. $actions (facultatif) : ce qui se range a droite du titre (un lien, un compte).
     Le filet deborde de la colonne de la meme marge que les lignes des listes et la bande de chiffres
     (-mx-4 sm:-mx-5) : le texte reste aligne sur le titre de la page, les traits vont un peu au-dela. --}}
<section {{ $attributes }}>
    <div class="-mx-4 flex items-end justify-between gap-4 border-b border-default px-4 pb-3 sm:-mx-5 sm:px-5">
        <div class="min-w-0">
            <h2 class="text-[16px] font-semibold leading-snug tracking-tight text-primary">{{ $title }}</h2>
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
