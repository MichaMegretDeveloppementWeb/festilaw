// Formulaire de categorie du back-office : onglet de langue actif, etat de chaque langue, adresse qui
// suit le nom et apercu du resultat dans Google.
// Le composant Livewire (App\Livewire\Admin\Blog\CategoryForm) garde l'etat des champs et enregistre.

import { registerAlpine, slugField } from './slug-field';

const blogCategoryForm = () => ({
    tab: 'en',
    dirty: false,

    ...slugField,

    text(locale, field) {
        return (this.$wire.texts?.[locale]?.[field] ?? '').trim();
    },

    // Une categorie est redigee dans une langue des que son nom y est saisi.
    state(locale) {
        return this.text(locale, 'name') === '' ? 'empty' : 'written';
    },

    // Ce que Google affichera, avec les memes replis que la page publique de la categorie.
    googleTitle(locale) {
        const name = this.text(locale, 'name');

        return this.text(locale, 'metaTitle') || (name === '' ? '' : `${name} · Blog · Festilaw`);
    },

    googleDescription(locale) {
        return this.text(locale, 'metaDescription') || this.text(locale, 'description');
    },
});

registerAlpine('blogCategoryForm', blogCategoryForm);
