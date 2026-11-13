{{-- Carte d'article. $card : BlogPostCardData. $heading : niveau du titre (h2 sur une liste, h3 sous un
     titre de section). L'image est un second lien vers l'article, masque aux lecteurs d'ecran pour ne pas
     annoncer deux fois la meme destination. --}}
@php $heading = $heading ?? 'h2'; @endphp
<article class="post-card">
    <a href="{{ $card->url }}" class="post-card__media" tabindex="-1" aria-hidden="true">
        @if ($card->picture)
            <x-web.blog.picture :picture="$card->picture" sizes="(min-width: 1000px) 370px, (min-width: 680px) 46vw, 92vw" class="post-card__image" />
        @endif
    </a>
    <div class="post-card__body">
        @if ($card->category)
            <a href="{{ $card->category->url }}" class="eyebrow post-card__category">{{ $card->category->name }}</a>
        @endif
        <{{ $heading }} class="post-card__title"><a href="{{ $card->url }}">{{ $card->title }}</a></{{ $heading }}>
        @if ($card->excerpt)
            <p class="post-card__excerpt">{{ $card->excerpt }}</p>
        @endif
        <time class="post-card__date" datetime="{{ $card->publishedAt->toDateString() }}">{{ $card->publishedLabel(app()->getLocale()) }}</time>
    </div>
</article>
