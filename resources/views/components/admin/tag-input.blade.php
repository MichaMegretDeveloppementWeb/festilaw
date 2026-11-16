@props(['id', 'placeholder' => '', 'max' => 40, 'maxLength' => 120, 'error' => false])
{{-- Champ a pastilles : on tape une valeur, Entree en fait une pastille dans le champ. La croix d'une
     pastille la retire, Retour arriere dans un champ vide retire la derniere, et un texte colle sur
     plusieurs lignes donne une pastille par ligne.
     Se lie a une propriete LISTE du composant par `wire:model="propriete"` (ou `x-model`), poses sur ce
     composant. Demande le script resources/js/admin/tag-input.js sur la page (`@vite` dans `@assets`). --}}
<div x-data="adminTagInput({ max: {{ (int) $max }}, maxLength: {{ (int) $maxLength }} })" x-modelable="tags"
    {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
    x-on:click="$refs.field.focus()" data-tag-input
    @class([
        'flex min-h-(--ui-touch) cursor-text flex-wrap items-center gap-1.5 rounded-lg bg-white px-2 py-1.5 ring-1 ring-inset transition-shadow focus-within:ring-2 sm:min-h-(--ui-field) dark:bg-gray-800',
        'ring-gray-200 focus-within:ring-gray-300 dark:ring-gray-700 dark:focus-within:ring-gray-600' => ! $error,
        'ring-red-300 focus-within:ring-red-400 dark:ring-red-500/50' => $error,
    ])>
    <template x-for="(tag, index) in tags" x-bind:key="tag">
        <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-elevated py-0.5 pl-2.5 pr-1 text-[12px] font-medium text-primary" data-tag>
            <span class="truncate" x-text="tag"></span>
            <button type="button" x-on:click.stop="remove(index)" x-bind:aria-label="@js(__('Retirer')) + ' ' + tag"
                class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-muted transition-colors hover:bg-gray-200 hover:text-primary dark:hover:bg-gray-700">
                <x-ui::icon name="x-mark" class="h-3 w-3" stroke-width="2" />
            </button>
        </span>
    </template>

    <input x-ref="field" id="{{ $id }}" type="text" x-model="draft" maxlength="{{ (int) $maxLength }}" autocomplete="off"
        x-bind:placeholder="tags.length === 0 ? @js($placeholder) : ''"
        x-on:keydown.enter.prevent="add()" x-on:keydown.backspace="removeLastWhenEmpty()" x-on:blur="add()" x-on:paste="paste($event)"
        class="min-w-[9rem] flex-1 border-0 bg-transparent px-1 py-0.5 text-[13px] leading-normal text-primary outline-none placeholder:text-muted focus:ring-0">
</div>
