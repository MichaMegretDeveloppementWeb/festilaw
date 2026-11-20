{{-- Pagination d'une liste d'articles. $pagination : BlogPaginationData.
     La cle « Pagination » est a eviter : elle designe le groupe lang/*/pagination.php et rend un tableau. --}}
@if ($pagination->hasPages())
    <nav class="blog-pagination" aria-label="{{ __('Article pages') }}" data-track-label="Blog, pagination">
        @if ($pagination->previousUrl())
            <a href="{{ $pagination->previousUrl() }}" class="blog-pagination__step" rel="prev">← {{ __('Newer articles') }}</a>
        @endif
        <ol class="blog-pagination__pages">
            @foreach ($pagination->pages() as $number)
                <li>
                    @if ($number === $pagination->currentPage)
                        <span class="blog-pagination__page is-current" aria-current="page"><span class="visually-hidden">{{ __('Page') }} </span>{{ $number }}</span>
                    @else
                        <a href="{{ $pagination->url($number) }}" class="blog-pagination__page"><span class="visually-hidden">{{ __('Page') }} </span>{{ $number }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
        @if ($pagination->nextUrl())
            <a href="{{ $pagination->nextUrl() }}" class="blog-pagination__step" rel="next">{{ __('Older articles') }} →</a>
        @endif
    </nav>
@endif
