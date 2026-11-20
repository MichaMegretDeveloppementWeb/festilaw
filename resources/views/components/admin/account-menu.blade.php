{{-- Le compte, en bas de la barre laterale, repris de Chouchoute-toi (demande de Micha du 20/11) : un menu
     du kit avec le compte, le site public et la deconnexion, qui quittent ainsi la barre du haut.
     `ui-sidebar-label` : les classes de repli du kit, qui masquent le texte quand la barre se replie.
     Le menu s'ouvre depuis son bouton, car la barre repliee couperait un panneau aussi large qu'elle. --}}
@props([
    'name' => '',
    'email' => '',
])

@php
    $initials = collect(explode(' ', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<x-ui::dropdown class="w-full">
    <x-slot:trigger>
        <button
            type="button"
            :aria-expanded="open"
            aria-haspopup="true"
            class="admin-account ui-sidebar-gap flex w-full items-center px-5 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
        >
            <x-ui::avatar :initials="$initials" size="default" class="shrink-0" />

            <div class="ui-sidebar-label min-w-0 flex-1 text-left">
                <p class="truncate text-[13px] font-medium text-gray-900 dark:text-gray-100">{{ $name !== '' ? $name : __('Mon compte') }}</p>
                <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $email }}</p>
            </div>

            <x-ui::icon name="chevron-up-down" class="ui-sidebar-label h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
        </button>
    </x-slot:trigger>

    <x-ui::dropdown-item :href="route('admin.profile')" icon="user-circle">
        {{ __('Mon compte') }}
    </x-ui::dropdown-item>

    <x-ui::dropdown-item :href="route('home')" icon="arrow-top-right-on-square" target="_blank" rel="noopener">
        {{ __('Voir le site public') }}
    </x-ui::dropdown-item>

    <x-ui::dropdown-item separator />

    {{-- La deconnexion est un POST : son formulaire a lui. --}}
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button
            type="submit"
            class="flex w-full items-center gap-x-2 px-3 py-2 text-[13px] text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
        >
            <x-ui::icon name="arrow-right-start-on-rectangle" class="h-4 w-4 text-red-400 dark:text-red-500" />
            {{ __('Se déconnecter') }}
        </button>
    </form>
</x-ui::dropdown>
