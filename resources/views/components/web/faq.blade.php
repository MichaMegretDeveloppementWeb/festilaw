@props(['items' => [], 'eyebrow' => 'FAQ', 'title' => __('Frequently asked questions')])
{{-- items : [['q' => '...', 'a' => '...'], ...]. Accordeon natif <details> (accessible, sans JS). --}}
<section class="faq">
    <div class="faq__inner">
        <div class="faq__head">
            <span class="eyebrow">{{ $eyebrow }}</span>
            <h2 class="faq__title">{{ $title }}</h2>
        </div>
        <div class="faq__list">
            @foreach ($items as $item)
                <details class="faq__item">
                    <summary class="faq__q">
                        <span>{{ $item['q'] }}</span>
                        <svg class="faq__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </summary>
                    <div class="faq__a">
                        <p>{{ $item['a'] }}</p>
                        @isset($item['link'])
                            <p class="faq__a-cta"><a href="{{ $item['link']['url'] }}">{{ $item['link']['text'] }}</a></p>
                        @endisset
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
