@props(['title', 'description' => null])
{{-- Un groupe de sections <x-admin.section> sur un ecran de reglages : un titre de 20 px, plus grand que celui des
     sections qu'il coiffe (18 px), puis une phrase qui dit a quoi le groupe s'applique. Micha a demande le 18/11
     que les reglages de la generation IA separent ce qui vaut pour chaque article, les valeurs par defaut et la
     generation automatique, puis le 19/11 que le titre d'un groupe ne soit plus plus petit que ses sections. Les
     sections s'ecartent d'elles-memes les unes des autres. --}}
<div {{ $attributes }}>
    <header class="mb-8">
        <h2 class="text-[20px] font-semibold leading-snug tracking-tight text-primary">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1.5 max-w-3xl text-[14px] leading-relaxed text-secondary">{{ $description }}</p>
        @endif
    </header>

    <div>
        {{ $slot }}
    </div>
</div>
