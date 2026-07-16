@props(['code', 'title', 'message'])
{{-- Bloc d'erreur brande, partage par les vues errors/{code}. --}}
<section class="error-page">
    <div class="error-page__inner">
        <span class="error-page__code">{{ $code }}</span>
        <h1 class="error-page__title">{{ $title }}</h1>
        <p class="error-page__message">{{ $message }}</p>
        <a href="{{ route('home') }}" class="btn btn--coral">{{ __('Back to home') }}</a>
    </div>
</section>
