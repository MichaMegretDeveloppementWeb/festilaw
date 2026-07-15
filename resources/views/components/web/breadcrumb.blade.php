@props(['items' => []])
{{-- items : [['name' => 'Home', 'url' => '...'], ...]. Le dernier element = page courante. --}}
<nav class="breadcrumb" aria-label="{{ __('Breadcrumb') }}">
    <ol class="breadcrumb__list">
        @foreach ($items as $item)
            <li class="breadcrumb__item">
                @unless ($loop->last)
                    <a href="{{ $item['url'] }}" class="breadcrumb__link">{{ $item['name'] }}</a>
                    <span class="breadcrumb__sep" aria-hidden="true">/</span>
                @else
                    <span class="breadcrumb__current" aria-current="page">{{ $item['name'] }}</span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
