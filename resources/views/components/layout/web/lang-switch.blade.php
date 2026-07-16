@php
    $labels = config('festilaw.locale_labels');
@endphp
<div {{ $attributes->merge(['class' => 'lang-switch']) }}>
    @foreach (config('festilaw.supported_locales') as $code)
        <a
            href="{{ route('locale.switch', ['locale' => $code]) }}"
            @class(['is-active' => app()->getLocale() === $code])
        >{{ $labels[$code] ?? strtoupper($code) }}</a>
    @endforeach
</div>
