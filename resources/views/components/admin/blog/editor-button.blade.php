@props(['label', 'action', 'icon' => null, 'active' => null, 'disabled' => null])
{{-- Bouton de la barre d'outils de l'editeur d'article. $action, $active et $disabled sont des expressions
     Alpine, evaluees dans la portee du formulaire (resources/js/admin/blog-post-form.js).
     $active : le bouton s'allume quand le curseur est dans cette mise en forme. --}}
<button type="button" x-on:click="{{ $action }}" title="{{ $label }}" aria-label="{{ $label }}"
    @if ($active)
        x-bind:aria-pressed="({{ $active }}).toString()"
        x-bind:class="({{ $active }}) ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-secondary hover:bg-elevated hover:text-primary'"
    @endif
    @if ($disabled) x-bind:disabled="{{ $disabled }}" @endif
    {{ $attributes->class([
        'flex min-h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-[12px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-40 max-sm:min-h-10 max-sm:min-w-10',
        'text-secondary hover:bg-elevated hover:text-primary' => ! $active,
    ]) }}>
    @if ($icon)
        <x-ui::icon :name="$icon" class="h-4 w-4" />
    @else
        {{ $slot }}
    @endif
</button>
