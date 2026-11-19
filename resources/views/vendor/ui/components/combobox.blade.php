@props([
    'options' => [],
    'placeholder' => 'Choisir...',
    'searchPlaceholder' => 'Rechercher...',
    'empty' => 'Aucun résultat',
    'error' => false,
    'disabled' => false,
])
@aware(['for' => null, 'hint' => null])

@php
    // Same contract as <x-ui::select> : an associative value => label array.
    $items = collect($options)
        ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])
        ->values();

    $ringClasses = $error
        ? 'ui:ring-red-300 ui:dark:ring-red-500/40 ui:focus-within:ring-red-500'
        : 'ui:ring-gray-200 ui:dark:ring-gray-700 ui:focus-within:ring-gray-900 ui:dark:focus-within:ring-gray-100';

    $disabledClasses = $disabled
        ? 'ui:bg-gray-50 ui:dark:bg-gray-800/50 ui:cursor-not-allowed'
        : 'ui:bg-white ui:dark:bg-gray-800';

    $fieldClasses = implode(' ', [
        // `relative`: the button's `::after` covers the frame, arrow and padding included.
        'ui:relative ui:flex ui:min-h-(--ui-touch) ui:sm:min-h-(--ui-field) ui:w-full ui:items-stretch ui:gap-1 ui:rounded-lg ui:px-3 ui:text-[13px] ui:leading-normal ui:ring-1 ui:ring-inset ui:focus-within:ring-2 ui:focus-within:ring-inset',
        $ringClasses,
        $disabledClasses,
    ]);

    // The button takes the focus, so it is what a label names and what a message describes.
    $triggerAttributes = $attributes->only(['id', 'aria-describedby'])
        ->merge(\Falcon\Ui\View\FieldMessage::describedBy($attributes, $for, $hint, $errors ?? null, (bool) $error));

    // A model binds the component, which follows it: a render empties a hidden field the server does not write.
    $model = $attributes->whereStartsWith(['wire:model', 'x-model']);
    $bound = $model->isNotEmpty();
@endphp

<div
    x-data="uiCombobox({ placeholder: @js($placeholder), bound: @js($bound) })"
    x-id="['ui-combobox-list', 'ui-combobox-option']"
    @click.outside.capture="closeFromOutside($event)"
    @if($bound) x-modelable="value" {{ $model }} @endif
    {{-- class dresses the container, which carries the width; the model binds
         it; everything else, starting with name, belongs to the field. --}}
    {{ $attributes->only('class')->ui(['class' => 'ui-combobox ui:relative']) }}
>
    <input x-ref="field" type="hidden" {{ $attributes->except(['class', 'id', 'aria-describedby'])->whereDoesntStartWith(['wire:model', 'x-model']) }} />

    <script x-ref="options" type="application/json">@json($items)</script>

    <div class="{{ $fieldClasses }}">
        <button
            type="button"
            x-ref="trigger"
            @click="toggle()"
            @keydown.down.prevent="toggle()"
            :aria-expanded="open.toString()"
            aria-haspopup="listbox"
            {{-- The control that takes the focus is this button, so the mark of
                 a refused field goes here and not on the hidden input, which no
                 reader announces and no focus can reach. --}}
            @if($error) aria-invalid="true" @endif
            @if($disabled) disabled @endif
            {{ $triggerAttributes }}
            {{-- No outline of its own: the frame's ring says the focus, round the whole field. --}}
            class="ui:flex ui:min-w-0 ui:flex-1 ui:items-center ui:py-1 ui:text-left ui:focus:outline-none ui:after:absolute ui:after:inset-0 {{ $disabled ? 'ui:cursor-not-allowed' : '' }}"
        >
            <span
                class="ui:truncate"
                :class="selected ? 'ui:text-primary' : 'ui:text-muted'"
                x-text="label"
            ></span>
        </button>

        @unless($disabled)
            {{-- Clearing the choice is a real need on a filter, and there is no empty option to go back to. --}}
            <button
                type="button"
                x-show="selected"
                x-cloak
                @click="write('')"
                class="ui:relative ui:z-10 ui:flex ui:shrink-0 ui:items-center ui:justify-center ui:self-center ui:rounded ui:p-1.5 ui:text-muted ui:transition-colors ui:hover:text-gray-600 ui:dark:hover:text-gray-300 ui:max-sm:min-h-(--ui-touch) ui:max-sm:min-w-(--ui-touch)"
                aria-label="Effacer la sélection"
            >
                <svg class="ui:h-3.5 ui:w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        @endunless

        <svg class="ui:h-4 ui:w-4 ui:shrink-0 ui:self-center ui:text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
    </div>

    {{-- wire:ignore: the panel is driven entirely by Alpine, and Livewire's
         morph, unable to find the x-for's cloned rows in the server HTML,
         re-evaluates them outside the loop. « item is not defined » on every
         render. --}}
    <div
        wire:ignore
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        class="ui:absolute ui:inset-x-0 ui:top-full ui:z-30 ui:mt-1.5 ui:overflow-hidden ui:rounded-lg ui:border ui:border-gray-200 ui:bg-surface ui:shadow-lg ui:dark:border-gray-700 ui:dark:shadow-2xl ui:dark:shadow-black/20"
    >
        <div class="ui:flex ui:items-center ui:gap-x-2 ui:border-b ui:border-subtle ui:px-3">
            <svg class="ui:h-4 ui:w-4 ui:shrink-0 ui:text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            <input
                x-ref="search"
                x-model="search"
                @keydown="onKeydown($event)"
                @input="active = 0"
                type="text"
                placeholder="{{ $searchPlaceholder }}"
                aria-label="{{ $searchPlaceholder }}"
                :aria-controls="$id('ui-combobox-list')"
                :aria-activedescendant="activeId"
                class="ui:flex-1 ui:border-0 ui:bg-transparent ui:py-2.5 ui:text-[13px] ui:leading-normal ui:text-primary ui:placeholder:text-muted ui:focus:ring-0"
            />
        </div>

        {{-- Bounded height, since the list comes from an entity and can hold hundreds of rows. --}}
        <ul x-ref="list" :id="$id('ui-combobox-list')" class="ui:max-h-64 ui:overflow-y-auto ui:p-1.5" role="listbox">
            <template x-for="(item, index) in filtered" :key="item.value">
                <li>
                    <button
                        type="button"
                        role="option"
                        tabindex="-1"
                        :id="$id('ui-combobox-option', index)"
                        @click="choose(item)"
                        @mouseenter="active = index"
                        :aria-selected="isChosen(item).toString()"
                        :class="index === active ? 'ui:bg-elevated' : ''"
                        class="ui:flex ui:w-full ui:items-center ui:justify-between ui:gap-2 ui:rounded-md ui:px-2.5 ui:py-2 ui:text-left ui:text-[13px] ui:text-gray-700 ui:transition-colors ui:dark:text-gray-300 ui:max-sm:min-h-11"
                    >
                        <span class="ui:truncate" x-text="item.label"></span>
                        <svg x-show="isChosen(item)" class="ui:h-4 ui:w-4 ui:shrink-0 ui:text-primary" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </button>
                </li>
            </template>

            <li x-show="filtered.length === 0" class="ui:px-2.5 ui:py-3 ui:text-[13px] ui:text-muted">{{ $empty }}</li>
        </ul>

        {{-- Festilaw, seul ajout a la vue du kit (publiee le 19/11/2026) : un emplacement facultatif sous la liste,
             pour une action qui complete le choix (« Nouvelle catégorie » dans un article, decision de Micha). Il est
             dans la portee de uiCombobox : son contenu lit `search` et ferme la liste par `open = false`. Sans lui,
             le composant est celui du kit. --}}
        @isset($action)
            <div class="border-t border-subtle p-1.5" data-combobox-action>
                {{ $action }}
            </div>
        @endisset
    </div>
</div>
