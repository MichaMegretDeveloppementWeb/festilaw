@props(['title', 'description' => null])
{{-- Une section d'un ecran de reglages : son titre et sa phrase d'explication a gauche, sa carte de champs a
     droite. Sur un ecran etroit, les deux moities s'empilent. La page prend ainsi toute sa largeur, sans
     colonne etroite au milieu. --}}
<section {{ $attributes->merge(['class' => 'grid gap-x-10 gap-y-4 lg:grid-cols-[18rem_minmax(0,1fr)] xl:grid-cols-[22rem_minmax(0,1fr)]']) }}>
    <div class="lg:pt-1">
        <h2 class="text-[15px] font-semibold leading-snug text-primary">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1.5 text-[13px] leading-relaxed text-muted">{{ $description }}</p>
        @endif
    </div>

    <x-ui::card>
        {{ $slot }}
    </x-ui::card>
</section>
