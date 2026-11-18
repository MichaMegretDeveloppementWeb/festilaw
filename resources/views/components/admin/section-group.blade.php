@props(['title', 'description' => null])
{{-- Un groupe de sections <x-admin.section> sur un ecran de reglages : un titre court en capitales, comme les
     groupes du menu lateral du kit mais dans la couleur du texte pour servir de repere, puis une phrase qui dit a
     quoi le groupe s'applique. Micha a
     demande le 18/11 que les reglages de la generation IA separent ce qui vaut pour chaque article, les valeurs
     par defaut et la generation automatique : melanges, on ne savait plus ce qui s'appliquait a quoi. --}}
<div {{ $attributes }}>
    <header class="mb-8">
        <h2 class="text-[12px] font-semibold uppercase tracking-wider text-primary">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1.5 max-w-3xl text-[13px] leading-relaxed text-secondary">{{ $description }}</p>
        @endif
    </header>

    <div class="space-y-12">
        {{ $slot }}
    </div>
</div>
