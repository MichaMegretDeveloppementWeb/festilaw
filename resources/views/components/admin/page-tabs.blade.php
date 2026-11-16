@props(['tabs'])
{{-- Onglets d'une rubrique en plusieurs pages : chaque onglet est un lien vers une vraie page.
     $tabs : liste de ['label' => ..., 'href' => ..., 'active' => bool].
     Les onglets se touchent et s'espacent par leur marge interieure : aucune zone morte entre deux. Le
     premier n'a pas de marge a gauche, pour que son texte et son trait partent du bord du contenu. --}}
<nav {{ $attributes->merge(['class' => 'border-b border-subtle']) }} aria-label="{{ __('Pages de cette rubrique') }}">
    <div class="-mb-px flex overflow-x-auto">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['href'] }}" @if ($tab['active']) aria-current="page" @endif
                @class([
                    'shrink-0 border-b-2 px-3 py-2.5 text-[13px] font-medium transition-colors first:pl-0',
                    'border-gray-900 text-gray-900 dark:border-white dark:text-white' => $tab['active'],
                    'border-transparent text-muted hover:text-secondary' => ! $tab['active'],
                ])>{{ $tab['label'] }}</a>
        @endforeach
    </div>
</nav>
