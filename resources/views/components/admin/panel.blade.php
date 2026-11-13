@props(['title', 'description' => null, 'variant' => 'default'])
{{-- Un bloc d'ecran du back-office : une carte du kit, un bandeau de titre, puis le contenu, qui pose
     lui-meme ses marges. $actions (facultatif) : ce qui se range a droite du titre. --}}
@php($danger = $variant === 'danger')
<x-ui::card :variant="$variant" :padding="false" {{ $attributes }}>
    <div @class([
        'flex items-center justify-between gap-3 border-b px-5 py-3.5',
        'border-subtle' => ! $danger,
        'border-red-100 dark:border-red-500/20' => $danger,
    ])>
        <div class="min-w-0">
            <h2 @class([
                'text-[13px] font-semibold',
                'text-primary' => ! $danger,
                'text-red-700 dark:text-red-400' => $danger,
            ])>{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-[12px] leading-relaxed text-muted">{{ $description }}</p>
            @endif
        </div>
        {{ $actions ?? '' }}
    </div>
    {{ $slot }}
</x-ui::card>
