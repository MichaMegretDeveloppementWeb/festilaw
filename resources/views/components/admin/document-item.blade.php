@props(['title', 'subtitle' => null, 'downloadUrl'])

{{-- Un fichier d'un dossier, en ligne d'une liste a filets (page blanche) : une icone sur carre gris neutre, le nom,
     sa precision, et le bouton de telechargement. La liste qui l'entoure pose les filets et deborde de la colonne
     (-mx-4 sm:-mx-5) ; la ligne reprend la meme marge interieure, pour que le texte reste aligne sur les titres. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 px-4 py-3.5 sm:px-5']) }}>
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-elevated text-secondary" aria-hidden="true">
        <x-ui::icon name="document-text" class="h-[18px] w-[18px]" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-[14px] font-medium text-primary">{{ $title }}</p>
        @if ($subtitle)
            <p class="truncate text-[12px] text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    <x-ui::button variant="secondary" size="compact" :href="$downloadUrl" class="shrink-0">
        <x-ui::icon name="arrow-down-tray" class="h-3.5 w-3.5" />
        {{ __('Télécharger') }}
    </x-ui::button>
</div>
