{{-- Article "a la une" du hub : le plus recent, en grand. $card : BlogPostCardData. --}}
<article class="post-featured">
    <a href="{{ $card->url }}" class="post-featured__media" tabindex="-1" aria-hidden="true">
        @if ($card->picture)
            <x-web.blog.picture :picture="$card->picture" sizes="(min-width: 900px) 620px, 92vw" :eager="true" class="post-featured__image" />
        @endif
    </a>
    <div class="post-featured__body">
        <span class="post-featured__flag">{{ __('Latest article') }}</span>
        @if ($card->category)
            <a href="{{ $card->category->url }}" class="eyebrow post-featured__category">{{ $card->category->name }}</a>
        @endif
        <h2 class="post-featured__title"><a href="{{ $card->url }}">{{ $card->title }}</a></h2>
        @if ($card->excerpt)
            <p class="post-featured__excerpt">{{ $card->excerpt }}</p>
        @endif
        <div class="post-featured__foot">
            <time class="post-featured__date" datetime="{{ $card->publishedAt->toDateString() }}">{{ $card->publishedLabel(app()->getLocale()) }}</time>
            <a href="{{ $card->url }}" class="post-featured__more" aria-hidden="true" tabindex="-1">{{ __('Read the article') }} →</a>
        </div>
    </div>
</article>
