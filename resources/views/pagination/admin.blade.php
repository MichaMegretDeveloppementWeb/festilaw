@if ($paginator->hasPages())
    <nav class="admin-pagination">
        @if ($paginator->onFirstPage())
            <span class="admin-pagination__link is-disabled">{{ __('Précédent') }}</span>
        @else
            <button type="button" class="admin-pagination__link" wire:click="previousPage" wire:loading.attr="disabled">{{ __('Précédent') }}</button>
        @endif

        <span class="admin-pagination__info">{{ __('Page') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <button type="button" class="admin-pagination__link" wire:click="nextPage" wire:loading.attr="disabled">{{ __('Suivant') }}</button>
        @else
            <span class="admin-pagination__link is-disabled">{{ __('Suivant') }}</span>
        @endif
    </nav>
@endif
