{{-- CTA flottant vers le tunnel, sur toutes les pages de contenu (masque dans le tunnel + l'espace client). --}}
@unless (request()->routeIs('get-started.*', 'my-project', 'find-my-project'))
    <a href="{{ route('get-started.index') }}" class="sticky-cta">{{ __('Get compliant in 24h') }}</a>
@endunless
