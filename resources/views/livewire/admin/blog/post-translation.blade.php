{{-- Traduction assistee : rien de visible, un releve toutes les 2 secondes tant qu'une traduction est attendue.
     Le bouton et la fenetre de confirmation sont dans le formulaire (post-form/translate.blade.php). --}}
<div data-post-translation>
    @if ($generationId)
        <div wire:poll.2s="check" wire:key="post-translation-poll-{{ $generationId }}"></div>
    @endif
</div>
