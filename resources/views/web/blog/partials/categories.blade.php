{{-- Menu des categories du blog. $categories : liste de BlogCategoryLinkData ; $allActive : sur le hub. --}}
@if ($categories !== [])
    <nav class="blog-cats" aria-label="{{ __('Blog categories') }}">
        <a href="{{ localized_route('blog.index') }}" @class(['blog-cats__link', 'is-active' => $allActive ?? false]) @if ($allActive ?? false) aria-current="page" @endif>{{ __('All articles') }}</a>
        @foreach ($categories as $category)
            <a href="{{ $category->url }}" @class(['blog-cats__link', 'is-active' => $category->isCurrent]) @if ($category->isCurrent) aria-current="page" @endif>{{ $category->name }}</a>
        @endforeach
    </nav>
@endif
