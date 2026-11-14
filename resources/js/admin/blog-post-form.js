// Formulaire d'article du back-office : ce qui vit dans le navigateur.
//  - le contenu des trois langues (remis une fois par le serveur, renvoye a chaque enregistrement),
//    redige dans un editeur par langue (./blog-editor.js), cree a la premiere ouverture de son onglet ;
//  - les images connues du formulaire (apercu et descriptions), tenues a jour au fil des envois ;
//  - l'onglet de langue actif et l'etat de chaque langue ;
//  - l'alerte de modifications non enregistrees ;
//  - la feuille des reglages sur telephone (sur ordinateur, le meme bloc est une colonne) ;
//  - l'apercu, ouvert dans un onglet des le clic pour ne pas etre bloque par le navigateur.
// Le composant Livewire (App\Livewire\Admin\Blog\PostForm) garde l'etat des champs et fait les ecritures.

import { IMAGE_DESCRIBE_EVENT, createBlogEditor, documentHasContent, repaintImages } from './blog-editor';
import { registerAlpine, slugField } from './slug-field';

const LOCALES = ['en', 'fr', 'es'];

// Largeur a partir de laquelle les reglages sont une colonne (palier `lg` de la feuille de style).
const WIDE = '(min-width: 64rem)';

// Adresses qu'un lien du texte peut porter : meme regle que le serveur (BlogLinkPolicy).
const LINK = /^(https?:\/\/\S+|mailto:\S+@\S+|\/(?!\/)\S*)$/i;

// Les editeurs restent hors de l'etat reactif : un editeur observe par Alpine cesse de fonctionner.
const editors = new Map();

// Envoie un fichier dans une propriete du composant. L'envoi de Livewire travaille par fonctions de
// rappel et ne rend rien : on en fait une promesse, qui rend true (fichier recu), false (envoi annule)
// ou echoue (refus ou coupure).
const sendFile = (wire, property, file) => new Promise((resolve, reject) => {
    wire.$upload(property, file, () => resolve(true), () => reject(new Error('upload failed')), () => {}, () => resolve(false));
});

const blogPostForm = (config) => ({
    tab: 'en',
    dirty: false,
    busy: false,
    // Document de contenu par langue. null tant que le bloc remis par le serveur n'a pas ete lu :
    // dans ce cas rien ne s'enregistre (on n'efface jamais un texte par accident).
    contents: null,
    // Images connues du formulaire : identifiant -> { id, url, alt: { en, fr, es } }.
    images: {},
    // Usage de l'image en cours d'envoi ('featured', 'share' ou 'content'), ou null.
    uploading: null,
    pendingImage: null,
    altForm: { id: null, en: '', fr: '', es: '', saving: false },
    linkForm: { locale: null, href: '', error: '' },
    // Change a chaque mouvement du curseur : la barre d'outils relit l'etat des boutons.
    pulse: 0,
    settingsOpen: false,
    wide: window.matchMedia(WIDE).matches,

    ...slugField,

    init() {
        this.contents = this.readContents();
        this.images = this.readJson('[data-blog-images]') ?? {};

        this.$nextTick(() => this.mountEditor(this.tab));
        this.$watch('tab', (locale) => this.$nextTick(() => this.mountEditor(locale)));
        this.$root.addEventListener(IMAGE_DESCRIBE_EVENT, (event) => this.describeImage(event.detail.imageId));

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

    destroy() {
        editors.forEach((editor) => editor.destroy());
        editors.clear();
    },

    readJson(selector) {
        try {
            const parsed = JSON.parse(this.$root.querySelector(selector)?.textContent ?? 'null');

            return parsed !== null && typeof parsed === 'object' ? parsed : null;
        } catch {
            return null;
        }
    },

    readContents() {
        const parsed = this.readJson('[data-blog-contents]');

        return parsed === null ? null : Object.fromEntries(LOCALES.map((locale) => [locale, parsed[locale] ?? null]));
    },

    // Une saisie dans le formulaire : il y a quelque chose a enregistrer. Ne comptent pas la zone de
    // recherche d'une liste avec recherche ni les champs marques data-no-dirty (fenetres qui
    // enregistrent elles-memes, choix d'un fichier).
    touched(event) {
        if (event.target.closest?.('[data-no-dirty]')) {
            return;
        }

        if (event.target.closest?.('.ui-combobox') && event.target.type !== 'hidden') {
            return;
        }

        this.dirty = true;
    },

    text(locale, field) {
        return (this.$wire.texts?.[locale]?.[field] ?? '').trim();
    },

    hasContent(locale) {
        return documentHasContent(this.contents?.[locale]);
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

    // --- Editeur -----------------------------------------------------------------------------

    mountEditor(locale) {
        if (editors.has(locale) || this.contents === null) {
            return;
        }

        const element = this.$root.querySelector(`[data-blog-editor="${locale}"]`);

        if (! element) {
            return;
        }

        editors.set(locale, createBlogEditor({
            element,
            locale,
            content: this.contents[locale],
            images: () => this.images,
            labels: config.labels,
            onChange: (document) => {
                this.contents[locale] = document;
                this.dirty = true;
            },
            onSelection: () => {
                this.pulse++;
            },
        }));
    },

    // Lance une commande de mise en forme sur l'editeur d'une langue.
    format(locale, command, ...parameters) {
        editors.get(locale)?.chain().focus()[command](...parameters).run();
    },

    isActive(locale, name, attributes = {}) {
        // Lecture de `pulse` : Alpine reevalue ce bouton a chaque mouvement du curseur.
        return this.pulse >= 0 && (editors.get(locale)?.isActive(name, attributes) ?? false);
    },

    can(locale, command) {
        return this.pulse >= 0 && (editors.get(locale)?.can()[command]?.() ?? false);
    },

    openLink(locale) {
        const editor = editors.get(locale);

        if (! editor) {
            return;
        }

        this.linkForm = { locale, href: editor.getAttributes('link').href ?? '', error: '' };
        this.$dispatch('ui-open-modal', 'post-link');
    },

    applyLink() {
        const editor = editors.get(this.linkForm.locale);
        const href = this.linkForm.href.trim();

        if (! editor) {
            return;
        }

        if (href === '') {
            this.removeLink();

            return;
        }

        if (! LINK.test(href)) {
            this.linkForm.error = config.labels.linkInvalid;

            return;
        }

        if (editor.state.selection.empty && ! editor.isActive('link')) {
            // Rien n'est selectionne : l'adresse devient le texte du lien.
            editor.chain().focus().insertContent({ type: 'text', text: href, marks: [{ type: 'link', attrs: { href } }] }).run();
        } else {
            editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        }

        this.$dispatch('ui-close-modal', 'post-link');
    },

    removeLink() {
        editors.get(this.linkForm.locale)?.chain().focus().extendMarkRange('link').unsetLink().run();
        this.$dispatch('ui-close-modal', 'post-link');
    },

    // --- Images ------------------------------------------------------------------------------

    // Ouvre le choix d'un fichier pour l'image principale, de partage, ou une image du texte.
    pickImage(usage, locale = null) {
        if (this.uploading) {
            return;
        }

        this.pendingImage = { usage, locale };
        this.$refs.imagePicker.click();
    },

    async uploadImage(event) {
        const file = event.target.files?.[0];
        const target = this.pendingImage;

        event.target.value = '';

        if (! file || ! target || this.uploading) {
            return;
        }

        // Un fichier trop lourd est refuse ici, avec la phrase du serveur. Au-dela de sa propre limite,
        // l'envoi de Livewire echoue sans motif, et le message parlerait a tort de la connexion.
        if (file.size > config.maxUploadBytes) {
            this.pendingImage = null;
            window.falconShowToast?.('error', config.labels.tooHeavy);

            return;
        }

        this.uploading = target.usage;

        try {
            const uploaded = await sendFile(this.$wire, 'imageUpload', file);

            if (! uploaded) {
                return;
            }

            const image = await this.$wire.storeImage(target.usage);

            if (! image) {
                return;
            }

            this.images[image.id] = image;
            this.dirty = true;

            if (target.usage === 'content') {
                editors.get(target.locale)?.chain().focus().insertBlogImage(image.id).run();
            }
        } catch {
            window.falconShowToast?.('error', config.labels.uploadFailed);
        } finally {
            this.uploading = null;
            this.pendingImage = null;
        }
    },

    removeImage(usage) {
        this.dirty = true;

        return usage === 'share' ? this.$wire.removeShareImage() : this.$wire.removeFeaturedImage();
    },

    // Ouvre la fenetre des descriptions d'une image (principale, de partage ou du texte).
    describeImage(imageId) {
        const image = this.images[imageId];

        if (! image) {
            return;
        }

        this.altForm = { id: imageId, saving: false, ...Object.fromEntries(LOCALES.map((locale) => [locale, image.alt?.[locale] ?? ''])) };
        this.$dispatch('ui-open-modal', 'image-alt');
    },

    async saveAlt() {
        if (this.altForm.saving || this.altForm.id === null) {
            return;
        }

        this.altForm.saving = true;

        try {
            const saved = await this.$wire.saveImageAlt(this.altForm.id, Object.fromEntries(LOCALES.map((locale) => [locale, this.altForm[locale]])));

            if (! saved) {
                return;
            }

            this.images[this.altForm.id].alt = saved;
            editors.forEach(repaintImages);
            this.$dispatch('ui-close-modal', 'image-alt');
        } finally {
            this.altForm.saving = false;
        }
    },

    // --- Enregistrement ----------------------------------------------------------------------

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
