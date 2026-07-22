@props(['title', 'subtitle' => null, 'downloadUrl'])

{{-- Presente un fichier comme un vrai document : vignette, nom, meta, et bouton de telechargement clair. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-lg border border-base bg-elevated px-3.5 py-3']) }}>
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface text-gray-500 ring-1 ring-inset ring-gray-200 dark:text-gray-400 dark:ring-gray-700">
        <x-ui.icon name="document-text" class="h-5 w-5" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-[13px] font-medium text-primary">{{ $title }}</p>
        @if ($subtitle)
            <p class="truncate text-[12px] text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    <x-ui.button variant="secondary" size="compact" href="{{ $downloadUrl }}" class="shrink-0">
        <x-ui.icon name="arrow-down-tray" class="h-3.5 w-3.5" />
        {{ __('Télécharger') }}
    </x-ui.button>
</div>
