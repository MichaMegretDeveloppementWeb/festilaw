@props(['status'])
{{-- Pastille de statut d'un article du blog. $status : App\Enums\Blog\BlogPostStatus. --}}
<x-ui::badge :color="match ($status) {
    \App\Enums\Blog\BlogPostStatus::Published => 'emerald',
    \App\Enums\Blog\BlogPostStatus::Scheduled => 'blue',
    default => 'gray',
}" {{ $attributes }}>{{ $status->label() }}</x-ui::badge>
