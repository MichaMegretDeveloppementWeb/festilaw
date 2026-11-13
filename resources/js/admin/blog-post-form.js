// Formulaire d'article du back-office : ce qui vit dans le navigateur.
//  - le contenu des trois langues (remis une fois par le serveur, renvoye a chaque enregistrement) ;
//  - l'onglet de langue actif et l'etat de chaque langue ;
//  - l'alerte de modifications non enregistrees ;
//  - la feuille des reglages sur telephone (sur ordinateur, le meme bloc est une colonne) ;
//  - l'apercu, ouvert dans un onglet des le clic pour ne pas etre bloque par le navigateur.
// Le composant Livewire (App\Livewire\Admin\Blog\PostForm) garde l'etat des champs et fait les ecritures.

import { registerAlpine, slugField } from './slug-field';

const LOCALES = ['en', 'fr', 'es'];

// Largeur a partir de laquelle les reglages sont une colonne (palier `lg` de la feuille de style).
const WIDE = '(min-width: 64rem)';

const blogPostForm = (config) => ({
    tab: 'en',
    dirty: false,
    busy: false,
    // Document de contenu par langue. null tant que le bloc remis par le serveur n'a pas ete lu :
    // dans ce cas rien ne s'enregistre (on n'efface jamais un texte par accident).
    contents: null,
    settingsOpen: false,
    wide: window.matchMedia(WIDE).matches,

    ...slugField,

    init() {
        this.contents = this.readContents();

        window.matchMedia(WIDE).addEventListener('change', (event) => {
            this.wide = event.matches;
            this.settingsOpen = false;
        });

        window.addEventListener('beforeunload', (event) => {
            if (this.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    },

    readContents() {
        try {
            const parsed = JSON.parse(this.$root.querySelector('[data-blog-contents]')?.textContent ?? 'null');

            if (parsed === null || typeof parsed !== 'object') {
                return null;
            }

            return Object.fromEntries(LOCALES.map((locale) => [locale, parsed[locale] ?? null]));
        } catch {
            return null;
        }
    },

    // Une saisie dans le formulaire : il y a quelque chose a enregistrer. La zone de recherche d'une
    // liste avec recherche ne compte pas, elle ne change rien a l'article.
    touched(event) {
        if (event.target.closest?.('.ui-combobox') && event.target.type !== 'hidden') {
            return;
        }

        this.dirty = true;
    },

    text(locale, field) {
        return (this.$wire.texts?.[locale]?.[field] ?? '').trim();
    },

    hasContent(locale) {
        return Array.isArray(this.contents?.[locale]?.content) && this.contents[locale].content.length > 0;
    },

    // Etat de REDACTION d'une langue (il ne dit rien de la publication) :
    // "empty" (rien), "written" (titre et contenu), "partial" sinon.
    state(locale) {
        const hasTitle = this.text(locale, 'title') !== '';
        const hasContent = this.hasContent(locale);

        if (hasTitle && hasContent) {
            return 'written';
        }

        return hasTitle || hasContent ? 'partial' : 'empty';
    },

    // Ce que Google affichera, avec les memes replis que la page publique.
    googleTitle(locale) {
        const title = this.text(locale, 'title');

        return this.text(locale, 'metaTitle') || (title === '' ? '' : `${title} · Festilaw`);
    },

    googleDescription(locale) {
        return this.text(locale, 'metaDescription') || this.text(locale, 'excerpt');
    },

    // Appelle une ecriture du composant en lui remettant le contenu. Rend l'identifiant de l'article,
    // ou null si rien n'a ete enregistre (validation, refus, erreur).
    async persist(method, ...args) {
        if (this.busy || this.contents === null) {
            return null;
        }

        const wasDirty = this.dirty;

        this.busy = true;
        this.dirty = false;

        try {
            const postId = await this.$wire[method](this.contents, ...args);

            if (postId === null || postId === undefined) {
                this.dirty = wasDirty;

                return null;
            }

            return postId;
        } catch {
            this.dirty = wasDirty;

            return null;
        } finally {
            this.busy = false;
        }
    },

    save() {
        return this.persist('save');
    },

    publish() {
        return this.persist('publish');
    },

    schedule() {
        return this.persist('schedule');
    },

    async preview(locale) {
        // Ouvert pendant le clic : un onglet ouvert apres l'enregistrement serait bloque.
        const tab = window.open('', '_blank');
        const postId = await this.persist('save');

        if (postId === null) {
            tab?.close();

            return;
        }

        if (tab) {
            tab.location = config.previewUrl.replace('__ID__', postId).replace('__LOCALE__', locale);
        }
    },
});

registerAlpine('blogPostForm', blogPostForm);
