@php
    $route = request()->route();
    $labels = config('festilaw.locale_labels');
@endphp
<div {{ $attributes->merge(['class' => 'lang-switch']) }}>
    @foreach (config('festilaw.supported_locales') as $code)
        <a
            href="{{ $route?->getName() ? route($route->getName(), array_merge($route->parameters(), ['locale' => $code])) : url($code) }}"
            hreflang="{{ $code }}"
            @class(['is-active' => app()->getLocale() === $code])
        >{{ $labels[$code] ?? strtoupper($code) }}</a>
    @endforeach
</div>
