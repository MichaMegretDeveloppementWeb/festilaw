@props(['status'])
{{-- Pastille de statut d'une generation d'article. $status : App\Enums\Blog\Generation\BlogGenerationStatus. --}}
<x-ui::badge :color="match ($status) {
    \App\Enums\Blog\Generation\BlogGenerationStatus::Completed => 'emerald',
    \App\Enums\Blog\Generation\BlogGenerationStatus::Running => 'blue',
    \App\Enums\Blog\Generation\BlogGenerationStatus::Queued => 'amber',
    \App\Enums\Blog\Generation\BlogGenerationStatus::Failed => 'red',
    default => 'gray',
}" {{ $attributes }}>{{ $status->label() }}</x-ui::badge>
