{{-- Menu des categories du blog. $categories : liste de BlogCategoryLinkData ; $allActive : sur le hub.
     Une seule ligne, qui defile quand elle depasse. Les fleches sont masquees au depart : le script ne les
     montre que du cote ou il reste des categories. Sans script, la ligne defile au doigt ou a la souris. --}}
@if ($categories !== [])
    <nav class="blog-cats" aria-label="{{ __('Blog categories') }}" data-blog-cats>
        <button data-track-label="Blog, catégories précédentes" type="button" class="blog-cats__arrow blog-cats__arrow--prev" aria-label="{{ __('Previous categories') }}" data-blog-cats-prev hidden>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
        <div class="blog-cats__track" data-blog-cats-track>
            <a data-track-label="Blog, tous les articles" href="{{ localized_route('blog.index') }}" @class(['blog-cats__link', 'is-active' => $allActive ?? false]) @if ($allActive ?? false) aria-current="page" @endif>{{ __('All articles') }}</a>
            @foreach ($categories as $category)
                <a data-track-label="Vers une catégorie, depuis le menu du blog" href="{{ $category->url }}" @class(['blog-cats__link', 'is-active' => $category->isCurrent]) @if ($category->isCurrent) aria-current="page" @endif>{{ $category->name }}</a>
            @endforeach
        </div>
        <button data-track-label="Blog, catégories suivantes" type="button" class="blog-cats__arrow blog-cats__arrow--next" aria-label="{{ __('Next categories') }}" data-blog-cats-next hidden>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
        </button>
    </nav>

    @once
        @push('scripts')
            @vite('resources/js/web/blog-categories.js')
        @endpush
    @endonce
@endif
