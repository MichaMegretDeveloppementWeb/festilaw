{{-- Formulaire d'article : le contenu a gauche, les reglages communs dans un panneau etroit a droite, et
     une barre d'actions qui reste en bas de l'ecran. Sur telephone, le panneau quitte la page et s'ouvre
     en feuille depuis le bouton « Réglages » de la barre.
     L'etat des champs est dans le composant ; le contenu des trois langues, l'onglet actif, la feuille des
     reglages et l'alerte de modifications non enregistrees vivent dans le navigateur (blogPostForm,
     resources/js/admin/blog-post-form.js). --}}
@php
    $status = $view?->status ?? \App\Enums\Blog\BlogPostStatus::Draft;
    $isPublished = $status === \App\Enums\Blog\BlogPostStatus::Published;
    $isScheduled = $status === \App\Enums\Blog\BlogPostStatus::Scheduled;
    $blockerCount = count($view?->blockers ?? []);
    $warningCount = count($view?->warnings ?? []);
    $heading = collect(['fr', 'en', 'es'])->map(fn ($locale) => trim((string) ($texts[$locale]['title'] ?? '')))->first(fn ($title) => $title !== '')
        ?? ($postId === null ? __('Nouvel article') : __('Article sans titre'));
@endphp
<div
    x-data="blogPostForm({ previewUrl: @js($previewUrlTemplate) })"
    x-on:input="touched($event)"
    x-on:change="touched($event)"
    x-on:blog-post-saved.window="dirty = false"
    x-on:keydown.escape.window="settingsOpen = false"
    class="flex min-h-[calc(100svh-6.5rem)] flex-col sm:min-h-[calc(100svh-7.5rem)]"
>
    {{-- Contenu des trois langues, remis une seule fois (texte echappe, lu par le script) : Livewire ne
         retouche pas ce bloc ensuite, et les rendus suivants n'y remettent rien. --}}
    <div hidden data-blog-contents wire:ignore>{{ $initialContents === null ? 'null' : json_encode($initialContents, JSON_THROW_ON_ERROR) }}</div>

    <div class="pb-8">
        @include('livewire.admin.blog.post-form.header')

        @error('post-load-failed')
            <x-ui::alert type="error" class="mb-6">{{ $message }}</x-ui::alert>
        @enderror

        @error('form')
            <x-ui::alert type="error" class="mb-6" :title="__('L\'article n\'a pas été enregistré')">{{ $message }}</x-ui::alert>
        @enderror

        @if ($view?->origin === \App\Enums\Blog\BlogPostOrigin::Ai)
            <x-ui::alert type="info" class="mb-6" :title="__('Article généré par IA')">
                {{ __('Ce brouillon a été proposé par l\'IA. Relisez-le et corrigez-le avant de le publier.') }}
            </x-ui::alert>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-6">
                <x-ui::card :padding="false">
                    <x-admin.blog.language-tabs :locales="$locales" />

                    @foreach ($locales as $locale => $language)
                        <div x-show="tab === '{{ $locale }}'" @if ($locale !== 'en') x-cloak @endif role="tabpanel" class="space-y-5 p-5">
                            @include('livewire.admin.blog.post-form.language', ['locale' => $locale, 'language' => $language])
                        </div>
                    @endforeach
                </x-ui::card>

                @include('livewire.admin.blog.post-form.sources')
            </div>

            @include('livewire.admin.blog.post-form.settings')
        </div>
    </div>

    @include('livewire.admin.blog.post-form.action-bar')
    @include('livewire.admin.blog.post-form.dialogs')

    @assets
        @vite('resources/js/admin/blog-post-form.js')
    @endassets
</div>
