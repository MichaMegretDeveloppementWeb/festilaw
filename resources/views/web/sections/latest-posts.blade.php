{{-- Bloc "derniers articles" de l'accueil. $posts : liste de BlogPostCardData, vide tant qu'aucun article
     n'est publie (le bloc n'est alors pas rendu). --}}
@if (($posts ?? []) !== [])
    <section class="latest-posts">
        <div class="latest-posts__inner">
            <div class="latest-posts__head">
                <span class="eyebrow">{{ __('Blog') }}</span>
                <h2 class="latest-posts__title">{{ __('Latest articles') }}</h2>
            </div>
            <div class="post-grid">
                @foreach ($posts as $card)
                    @include('web.blog.partials.card', ['card' => $card, 'heading' => 'h3'])
                @endforeach
            </div>
            <p class="latest-posts__more">
                <a href="{{ localized_route('blog.index') }}" class="latest-posts__more-link">{{ __('See all articles') }} →</a>
            </p>
        </div>
    </section>
@endif
