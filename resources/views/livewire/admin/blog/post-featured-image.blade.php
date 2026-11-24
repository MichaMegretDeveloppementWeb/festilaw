{{-- Image principale demandee depuis l'article : rien de visible, un releve toutes les 2 secondes tant qu'une image
     est attendue. Le bouton, son menu et la fenetre « Utiliser cette image ? » sont dans le formulaire
     (post-form/find-image.blade.php, dialogs.blade.php). --}}
<div data-post-featured-image>
    @if ($generationId)
        <div wire:poll.2s="check" wire:key="post-featured-image-poll-{{ $generationId }}"></div>
    @endif
</div>
