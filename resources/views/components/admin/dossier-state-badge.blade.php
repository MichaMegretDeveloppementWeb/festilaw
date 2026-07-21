@props(['state'])
@php
    $classes = match ($state->severity()) {
        'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'warn' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'bad' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'neutral' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'done' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'muted' => 'bg-slate-100 text-slate-400 ring-slate-400/20',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $classes }}">{{ $state->label() }}</span>
