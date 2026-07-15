@props([
    'plan',
    'price',
    'scope' => null,
    'included' => [],
    'note' => null,
])

{{-- Recapitulatif du parcours + repere de reassurance (paiement securise, RGPD, sans compte). --}}
<aside class="funnel-summary">
    <div class="funnel-summary__plan">
        <span class="funnel-summary__name">{{ $plan }}</span>
        <span class="funnel-summary__price">{{ $price }}</span>
        @if ($scope)
            <span class="funnel-summary__scope">{{ $scope }}</span>
        @endif
    </div>

    @if (count($included) > 0)
        <ul class="funnel-summary__list">
            @foreach ($included as $item)
                <li>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="funnel-summary__trust">
        <span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            {{ __('Secure payment') }}
        </span>
        <span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            {{ __('GDPR-compliant') }}
        </span>
        <span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            {{ __('No account needed') }}
        </span>
    </div>

    @if ($note)
        <p class="funnel-summary__note">{{ $note }}</p>
    @endif
</aside>
