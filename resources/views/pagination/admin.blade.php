@if ($paginator->hasPages())
    <nav class="mt-5 flex items-center gap-3">
        @if ($paginator->onFirstPage())
            <span class="cursor-default rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-300">{{ __('Précédent') }}</span>
        @else
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100" wire:click="previousPage" wire:loading.attr="disabled">{{ __('Précédent') }}</button>
        @endif

        <span class="text-sm text-slate-500">{{ __('Page') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100" wire:click="nextPage" wire:loading.attr="disabled">{{ __('Suivant') }}</button>
        @else
            <span class="cursor-default rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-300">{{ __('Suivant') }}</span>
        @endif
    </nav>
@endif
