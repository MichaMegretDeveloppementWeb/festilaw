{{-- Notice RGPD au point de collecte (Art. 13/14) : information + lien vers la politique. --}}
<p class="form-privacy" data-track-label="Formulaire, politique de confidentialité">
    {!! __('By submitting this form, you agree to how we handle your data, as described in our :privacy.', ['privacy' => '<a href="'.localized_route('privacy-policy').'">'.e(__('privacy policy')).'</a>']) !!}
</p>
