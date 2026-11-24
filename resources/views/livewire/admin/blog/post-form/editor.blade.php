{{-- Editeur du contenu d'une langue. $locale, $language (nom de la langue en francais).
     La barre d'outils parle a l'editeur par le formulaire (format, isActive, openLink, openImageDialog) ; dans
     le texte a cote d'une image, l'intertitre de niveau 2, la citation et l'image sont desactives (inSideText) ;
     la zone de saisie est creee par le script a la premiere ouverture de l'onglet, et Livewire n'y
     touche jamais (wire:ignore). --}}
@php
    $separator = '<span class="mx-1 h-5 w-px shrink-0 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>';
@endphp
<div>
    <p class="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-300" @if ($view?->isMissing('content', $locale)) data-missing="content" @endif>{{ __('Contenu') }}</p>

    {{-- Une bordure, pas un anneau interieur : la barre d'outils, qui reste en haut de l'ecran pendant qu'on
         fait defiler un long article, a son propre fond et couvrirait l'anneau. --}}
    <div class="rounded-lg border border-gray-200 bg-white focus-within:border-gray-900 focus-within:ring-1 focus-within:ring-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:focus-within:border-gray-100 dark:focus-within:ring-gray-100">
        <div class="sticky top-0 z-10 flex flex-wrap items-center gap-0.5 rounded-t-[7px] border-b border-subtle bg-white px-1.5 py-1.5 dark:bg-gray-800" role="toolbar" aria-label="{{ __('Mise en forme du contenu en :language', ['language' => $language]) }}">
            <x-admin.blog.editor-button :label="__('Intertitre')" icon="h2"
                action="format('{{ $locale }}', 'toggleHeading', { level: 2 })" active="isActive('{{ $locale }}', 'heading', { level: 2 })"
                disabled="inSideText('{{ $locale }}')" />
            <x-admin.blog.editor-button :label="__('Sous-intertitre')" icon="h3"
                action="format('{{ $locale }}', 'toggleHeading', { level: 3 })" active="isActive('{{ $locale }}', 'heading', { level: 3 })" />
            {!! $separator !!}
            <x-admin.blog.editor-button :label="__('Gras')" icon="bold"
                action="format('{{ $locale }}', 'toggleBold')" active="isActive('{{ $locale }}', 'bold')" />
            <x-admin.blog.editor-button :label="__('Italique')" icon="italic"
                action="format('{{ $locale }}', 'toggleItalic')" active="isActive('{{ $locale }}', 'italic')" />
            {!! $separator !!}
            <x-admin.blog.editor-button :label="__('Liste à puces')" icon="list-bullet"
                action="format('{{ $locale }}', 'toggleBulletList')" active="isActive('{{ $locale }}', 'bulletList')" />
            <x-admin.blog.editor-button :label="__('Liste numérotée')" icon="numbered-list"
                action="format('{{ $locale }}', 'toggleOrderedList')" active="isActive('{{ $locale }}', 'orderedList')" />
            <x-admin.blog.editor-button :label="__('Citation')" icon="chat-bubble-bottom-center-text"
                action="format('{{ $locale }}', 'toggleBlockquote')" active="isActive('{{ $locale }}', 'blockquote')"
                disabled="inSideText('{{ $locale }}')" />
            {!! $separator !!}
            <x-admin.blog.editor-button :label="__('Lien')" icon="link"
                action="openLink('{{ $locale }}')" active="isActive('{{ $locale }}', 'link')" />
            <x-admin.blog.editor-button :label="__('Image dans le texte')" icon="photo"
                action="openImageDialog('{{ $locale }}')" disabled="inSideText('{{ $locale }}')" />
            {!! $separator !!}
            <x-admin.blog.editor-button :label="__('Annuler la dernière modification')" icon="arrow-uturn-left"
                action="format('{{ $locale }}', 'undo')" disabled="! can('{{ $locale }}', 'undo')" />
            <x-admin.blog.editor-button :label="__('Rétablir')" icon="arrow-uturn-right"
                action="format('{{ $locale }}', 'redo')" disabled="! can('{{ $locale }}', 'redo')" />

        </div>

        <div wire:ignore data-blog-editor="{{ $locale }}"></div>
    </div>

    <p class="mt-1.5 text-[12px] text-muted">{{ __('La touche Entrée crée un paragraphe. Maj + Entrée passe à la ligne dans le même paragraphe.') }}</p>
</div>
