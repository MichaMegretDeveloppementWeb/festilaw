{{-- Le choix de la mesure d'audience, a la section « Cookies » de la politique de confidentialite (decision de
     Micha du 24/11/2026) : ce que vit ce navigateur aujourd'hui, puis le bouton qui le change. Le formulaire
     revient a ce passage de la politique, dans la langue de la page (AudienceMeasurementController). Les
     libelles de mesure du bouton sont fixes, en francais, comme tout element cliquable du site public. --}}
@inject('measurementChoice', \App\Services\Analytics\AudienceMeasurementChoice::class)
@php
    $measurementRefused = $measurementChoice->isRefused(request());
@endphp
<form method="POST" action="{{ route('audience-measurement') }}" id="audience-measurement" class="legal__measurement" data-measurement-state="{{ $measurementRefused ? 'refused' : 'measured' }}">
    @csrf
    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
    <input type="hidden" name="choice" value="{{ $measurementRefused ? 'accept' : 'refuse' }}">
    <p>{{ $measurementRefused ? __('Your visits are no longer counted in the audience measurement.') : __('Your visits are counted in the audience measurement.') }}</p>
    <button type="submit" class="btn btn--sm btn--outline-dark" data-track-label="{{ $measurementRefused ? 'Mesure d\'audience, compter de nouveau' : 'Mesure d\'audience, ne plus compter' }}">
        {{ $measurementRefused ? __('Count my visits again') : __('Stop counting my visits') }}
    </button>
</form>
