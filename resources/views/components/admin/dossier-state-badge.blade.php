@props(['state'])
@php
    $color = match ($state->severity()) {
        'ok' => 'emerald',
        'warn' => 'amber',
        'bad' => 'red',
        'neutral' => 'blue',
        default => 'gray', // done, muted
    };
@endphp
<x-ui.badge :color="$color" dot>{{ $state->label() }}</x-ui.badge>
