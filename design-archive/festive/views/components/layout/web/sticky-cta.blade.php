{{-- Barre CTA fixe, fermable en CSS pur (bascule reprise en Alpine avec Livewire). --}}
<input type="checkbox" id="sticky-bar-dismiss" class="sticky-bar__toggle" hidden>
<div class="sticky-bar">
    <span class="sticky-bar__text">Ready to secure your sales in Europe? <span class="sticky-bar__accent">Get compliant in 24h.</span></span>
    <a href="{{ route('home') }}#pricing" class="btn btn--coral btn--sm">Get My Mandate Now</a>
    <label for="sticky-bar-dismiss" class="sticky-bar__close" aria-label="Dismiss">&times;</label>
</div>
