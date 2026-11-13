@props(['field', 'recommended'])
{{-- Compteur de caracteres d'un champ, pose sur la ligne de son libelle (le parent est en `relative`).
     $field : expression Alpine de la valeur ($wire.texts.fr.title) ; $recommended : longueur conseillee.
     Une indication, pas une limite : au-dela, le compteur passe a l'orange. --}}
<span {{ $attributes->merge(['class' => 'pointer-events-none absolute right-0 top-0.5 text-[11px] tabular-nums']) }}
    x-bind:class="(({{ $field }}) || '').length > {{ (int) $recommended }} ? 'font-medium text-amber-700 dark:text-amber-400' : 'text-muted'">
    <span x-text="(({{ $field }}) || '').length">0</span>&nbsp;/&nbsp;{{ (int) $recommended }}
    <span class="sr-only">{{ __('caractères, :count conseillés au plus', ['count' => (int) $recommended]) }}</span>
</span>
