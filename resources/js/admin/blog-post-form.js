// Formulaire d'article du back-office : ce qui vit dans le navigateur.
//  - le contenu des trois langues (remis une fois par le serveur, renvoye a chaque enregistrement),
//    redige dans un editeur par langue (./blog-editor.js), cree a la premiere ouverture de son onglet ;
//  - les images connues du formulaire (apercu et descriptions), tenues a jour au fil des envois ;
//  - la fenetre « Image dans le texte » : choix du fichier, recadrage, largeur, description ;
//  - l'onglet de langue actif et l'etat de chaque langue ;
//  - l'alerte de modifications non enregistrees ;
//  - la feuille des reglages sur telephone (sur ordinateur, le meme bloc est une colonne) ;
//  - la saisie d'une source et la recherche d'un article a lier, chacune dans sa fenetre ;
//  - l'apercu, ouvert dans un onglet des le clic pour ne pas etre bloque par le navigateur.
// Le composant Livewire (App\Livewire\Admin\Blog\PostForm) garde l'etat des champs et fait les ecritures.

import { IMAGE_DESCRIBE_EVENT, IMAGE_RECROP_EVENT, createBlogEditor, documentHasContent, repaintImages, replaceImageAt } from './blog-editor';
import { createImageCropper } from './blog-image-cropper';
import { registerAlpine, slugField } from './slug-field';

const LOCALES = ['en', 'fr', 'es'];

// Largeur a partir de laquelle les reglages sont une colonne (palier `lg` de la feuille de style).
const WIDE = '(min-width: 64rem)';

// Adresses qu'un lien du texte peut porter : meme regle que le serveur (BlogLinkPolicy).
const LINK = /^(https?:\/\/\S+|mailto:\S+@\S+|\/(?!\/)\S*)$/i;

// Les editeurs restent hors de l'etat reactif : un editeur observe par Alpine cesse de fonctionner.
const editors = new Map();

// L'outil de recadrage de la fenetre d'image, hors de l'etat reactif lui aussi, et l'adresse locale du
// fichier choisi (a liberer quand la fenetre se ferme).
let cropper = null;
let localImageUrl = null;

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

// Fenetre d'image remise a zero. `mode` : 'new' (une image a ajouter) ou 'recrop' (un nouveau cadrage).
const emptyImageForm = () => ({
    mode: 'new', locale: null, imageId: null, position: null, file: null, preview: '', ready: false,
    ratio: 'free', zoom: 0, width: 'full', align: 'center', alt: '', busy: false, error: '',
});

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
    imageForm: emptyImageForm(),
    // Source en cours de saisie dans sa fenetre. `index` nul : une nouvelle source.
    sourceForm: { index: null, label: '', url: '', errors: {}, saving: false },
    // Categorie creee depuis la liste des categories : son nom dans les trois langues, et les refus du serveur.
    categoryForm: { names: { en: '', fr: '', es: '' }, errors: {}, saving: false },
    // Ce qui est tape dans la recherche de la fenetre des articles a lier.
    relatedQuery: '',
    // Change a chaque mouvement du curseur : la barre d'outils relit l'etat des boutons.
    pulse: 0,
    settingsOpen: false,
    // Traduction assistee : la langue de depart et la langue visee de la demande en cours ; `field`, le seul
    // champ demande, ou null pour toute la langue ; `pending`, la langue en attente de sa traduction (le
    // bouton le dit), ou null.
    translation: { from: null, to: null, field: null, pending: null },
    // Image principale demandee depuis l'article (24/11/2026) : `pending`, la source en cours (« stock » ou
    // « generated »), ou null ; `candidate`, l'image trouvee montree avant de remplacer l'actuelle.
    imageSearch: { pending: null, candidate: null },
    wide: window.matchMedia(WIDE).matches,

    ...slugField,

    init() {
        this.contents = this.readContents();
        this.images = this.readJson('[data-blog-images]') ?? {};

        this.$nextTick(() => this.mountEditor(this.tab));
        this.$watch('tab', (locale) => this.$nextTick(() => this.mountEditor(locale)));
        this.$root.addEventListener(IMAGE_DESCRIBE_EVENT, (event) => this.describeImage(event.detail.imageId));
        this.$root.addEventListener(IMAGE_RECROP_EVENT, (event) => this.openImageRecrop(event.detail));

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
        this.releaseCropper();
    },

    // Une fenetre du kit vient de se fermer, par son bouton, sa croix, la touche Echap ou un clic a cote.
    modalClosed(name) {
        if (name === 'post-related') {
            this.$wire.closeRelatedPicker();
        }

        if (name === 'post-image') {
            this.releaseCropper();
            this.imageForm = emptyImageForm();
        }
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

    // Le curseur est dans le texte a cote d'une image : ni intertitre de niveau 2, ni citation, ni image n'y
    // sont admis (le serveur les retirerait), les boutons correspondants se desactivent.
    inSideText(locale) {
        return this.isActive(locale, 'blogImageText');
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

    // Ouvre le choix d'un fichier pour l'image principale ou l'image de partage. Une image du texte passe
    // par sa fenetre (openImageDialog), qui la recadre avant l'envoi.
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

    // --- Image dans le texte : fenetre d'ajout et de recadrage -------------------------------

    // Ouvre la fenetre pour ajouter une image dans le texte de cette langue.
    openImageDialog(locale) {
        this.releaseCropper();
        this.imageForm = { ...emptyImageForm(), locale };
        this.$dispatch('ui-open-modal', 'post-image');
    },

    // Ouvre la fenetre sur une image deja placee, pour lui donner un autre cadrage a partir de sa source.
    openImageRecrop({ imageId, locale, position }) {
        const image = this.images[imageId];

        if (! image?.source) {
            return;
        }

        this.releaseCropper();
        this.imageForm = { ...emptyImageForm(), mode: 'recrop', locale, imageId, position };
        this.$dispatch('ui-open-modal', 'post-image');
        this.showInCropper(image.source, image.crop);
    },

    chooseImageFile(event) {
        const file = event.target.files?.[0];

        event.target.value = '';
        this.acceptImageFile(file);
    },

    dropImageFile(event) {
        this.acceptImageFile(event.dataTransfer?.files?.[0]);
    },

    // Le fichier est refuse ici, avec la phrase du serveur, avant tout envoi : format, puis poids.
    acceptImageFile(file) {
        if (! file) {
            return;
        }

        if (! IMAGE_TYPES.includes(file.type)) {
            this.imageForm.error = config.labels.wrongType;

            return;
        }

        if (file.size > config.maxUploadBytes) {
            this.imageForm.error = config.labels.tooHeavy;

            return;
        }

        this.releaseCropper();
        localImageUrl = URL.createObjectURL(file);
        this.imageForm = { ...this.imageForm, file, error: '', ratio: 'free', zoom: 0 };
        this.showInCropper(localImageUrl);
    },

    // Affiche une image dans l'outil de recadrage, des qu'elle est chargee. `crop` : cadrage a reprendre.
    showInCropper(url, crop = null) {
        this.imageForm.preview = url;
        this.imageForm.ready = false;

        this.$nextTick(() => {
            const picture = document.getElementById('post-image-canvas');

            if (! picture) {
                return;
            }

            const start = async () => {
                // L'outil prend la taille de sa zone au moment ou il est cree. Une image deja en cache peut
                // arriver avant que la fenetre soit affichee : on attend que la zone ait une largeur.
                for (let frame = 0; frame < 180 && picture.parentElement.offsetWidth === 0; frame++) {
                    await new Promise((resolve) => { requestAnimationFrame(resolve); });
                }

                // La fenetre a pu etre fermee, ou une autre image choisie, pendant le chargement.
                if (this.imageForm.preview !== url) {
                    return;
                }

                const created = await createImageCropper(picture, { crop, onZoom: (position) => { this.imageForm.zoom = position; } });

                if (this.imageForm.preview !== url) {
                    created.destroy();

                    return;
                }

                cropper = created;
                this.imageForm.ready = true;
            };

            picture.onload = start;
            picture.onerror = () => { this.imageForm.error = config.labels.unreadable; };
            picture.src = url;
        });
    },

    setImageRatio() {
        cropper?.ratio(this.imageForm.ratio);
    },

    zoomImage() {
        cropper?.zoom(Number(this.imageForm.zoom));
    },

    // « Changer d'image » : retour au choix du fichier.
    resetImageFile() {
        this.releaseCropper();
        this.imageForm = { ...emptyImageForm(), locale: this.imageForm.locale, width: this.imageForm.width, align: this.imageForm.align, alt: this.imageForm.alt };
    },

    releaseCropper() {
        cropper?.destroy();
        cropper = null;

        if (localImageUrl !== null) {
            URL.revokeObjectURL(localImageUrl);
            localImageUrl = null;
        }

        const picture = document.getElementById('post-image-canvas');

        if (picture) {
            picture.onload = null;
            picture.onerror = null;
            picture.removeAttribute('src');
        }
    },

    // Envoie l'image avec son cadrage (le serveur decoupe), ou demande un nouveau cadrage d'une image placee.
    async submitImage() {
        const form = this.imageForm;

        if (! cropper || ! form.ready || form.busy) {
            return;
        }

        const crop = cropper.crop();
        const editor = editors.get(form.locale);

        this.imageForm.busy = true;
        this.imageForm.error = '';

        try {
            if (form.mode === 'recrop') {
                const image = await this.$wire.recropImage(form.imageId, crop);

                if (! image) {
                    return;
                }

                this.images[image.id] = image;
                replaceImageAt(editor, form.position, image.id);
            } else {
                const uploaded = await sendFile(this.$wire, 'imageUpload', form.file);

                if (! uploaded) {
                    return;
                }

                const image = await this.$wire.storeImage('content', crop, { [form.locale]: form.alt });

                if (! image) {
                    return;
                }

                this.images[image.id] = image;
                editor?.chain().focus().insertBlogImage(image.id, form.width, form.align).run();
            }

            this.dirty = true;
            this.$dispatch('ui-close-modal', 'post-image');
        } catch {
            this.imageForm.error = config.labels.uploadFailed;
        } finally {
            this.imageForm.busy = false;
        }
    },

    // --- Sources et articles lies ------------------------------------------------------------

    // Ouvre la fenetre d'une source : vide pour en ajouter une, remplie pour modifier celle du rang donne.
    openSource(index = null, label = '', url = '') {
        this.sourceForm = { index, label, url, errors: {}, saving: false };
        this.$dispatch('ui-open-modal', 'post-source');
    },

    // Le serveur controle la source et rend ses refus par champ ; une liste vide veut dire qu'elle est
    // entree dans la liste.
    async saveSource() {
        if (this.sourceForm.saving) {
            return;
        }

        this.sourceForm.saving = true;

        try {
            const errors = await this.$wire.saveSource(this.sourceForm.index, this.sourceForm.label, this.sourceForm.url);

            if (errors && Object.keys(errors).length > 0) {
                this.sourceForm.errors = errors;

                return;
            }

            this.dirty = true;
            this.$dispatch('ui-close-modal', 'post-source');
        } finally {
            this.sourceForm.saving = false;
        }
    },

    // Ouvre la fenetre d'une nouvelle categorie (action de la liste des categories, decision de Micha du 19/11) :
    // le texte tape dans la recherche est deja ecrit dans la langue de l'onglet ouvert.
    openCategory(name = '') {
        this.categoryForm = { names: { en: '', fr: '', es: '' }, errors: {}, saving: false };
        this.categoryForm.names[this.tab] = name;
        this.$dispatch('ui-open-modal', 'post-category');
    },

    // Le serveur cree la categorie et la choisit pour l'article, ou rend ses refus par champ.
    async createCategory() {
        if (this.categoryForm.saving) {
            return;
        }

        this.categoryForm.saving = true;

        try {
            const errors = await this.$wire.createCategory(this.categoryForm.names);

            if (errors && Object.keys(errors).length > 0) {
                this.categoryForm.errors = errors;

                return;
            }

            this.dirty = true;
            this.$dispatch('ui-close-modal', 'post-category');
        } finally {
            this.categoryForm.saving = false;
        }
    },

    // Ouvre la fenetre des articles a lier tout de suite ; la liste arrive avec la reponse du serveur.
    openRelated() {
        this.relatedQuery = '';
        this.$dispatch('ui-open-modal', 'post-related');
        this.$wire.openRelatedPicker();
    },

    // Recherche dans les titres sans tenir compte de la casse ni des accents.
    matchesQuery(label, query) {
        const plain = (value) => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

        return plain(label).includes(plain(query.trim()));
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

    // --- Traduction assistee ---------------------------------------------------------------------------

    // « Traduire depuis… » dans l'onglet ouvert : la langue de l'onglet est remplie a partir de `from`, en entier,
    // ou seulement le champ `field` (bouton sous chaque champ, demande de Micha du 24/11/2026). Si ce qui sera
    // remplace a deja du texte, une fenetre demande d'abord confirmation.
    askTranslation(from, field = null) {
        this.translation = { from, to: this.tab, field, pending: this.translation.pending };
        const replacesText = field === null ? this.state(this.tab) !== 'empty' : this.fieldHasText(this.tab, field);

        if (replacesText) {
            this.$dispatch('ui-open-modal', field === null ? 'translate-post' : 'translate-field');

            return;
        }

        this.requestTranslation();
    },

    // Le champ a-t-il du texte dans cette langue ? Le contenu se lit dans l'editeur.
    fieldHasText(locale, field) {
        return field === 'content' ? this.hasContent(locale) : this.text(locale, field) !== '';
    },

    // Le texte de depart part tel qu'il est dans le formulaire, enregistre ou non. Le composant de traduction
    // le confie au worker, puis rend le resultat par l'evenement `post-translation-ready`. Pour un seul champ,
    // le serveur ne garde que lui.
    requestTranslation() {
        const { from, to, field } = this.translation;

        if (from === null || to === null || this.translation.pending !== null || this.contents === null) {
            return;
        }

        this.$dispatch('ui-close-modal', 'translate-post');
        this.$dispatch('ui-close-modal', 'translate-field');
        this.translation.pending = to;

        Livewire.dispatch('request-post-translation', {
            from,
            to,
            field,
            frenchTitle: this.text('fr', 'title'),
            source: {
                title: this.text(from, 'title'),
                excerpt: this.text(from, 'excerpt'),
                metaTitle: this.text(from, 'metaTitle'),
                metaDescription: this.text(from, 'metaDescription'),
                content: this.contents[from] ?? null,
            },
        });
    },

    // La traduction est prete : les champs et l'editeur de la langue sont remplis, rien n'est enregistre. Une
    // traduction d'un seul champ ne remplace que lui ; l'adresse suit le titre traduit, sauf si elle a ete
    // changee a la main.
    translationReady({ locale, texts, content, field = null }) {
        this.translation.pending = null;
        this.translation.field = null;

        if (field === null || field !== 'content') {
            for (const [name, value] of Object.entries(texts ?? {})) {
                if (field === null || name === field) {
                    this.$wire.texts[locale][name] = value ?? '';
                }
            }
        }

        if (field === null || field === 'content') {
            this.contents[locale] = content;
            editors.get(locale)?.commands.setContent(content);
            this.contents[locale] = content;
        }

        this.dirty = true;

        if (field === null || field === 'title') {
            this.suggestSlug(locale);
        }
    },

    // --- Image principale trouvee depuis l'article ---------------------------------------------------------------

    // « Image automatique » : la demande part avec les titres et extraits du formulaire, enregistres ou non, et
    // l'image actuelle, pour en obtenir une autre. Le composant PostFeaturedImage la confie au worker.
    askImage(mode) {
        if (this.imageSearch.pending !== null || this.uploading !== null) {
            return;
        }

        const locales = ['en', 'fr', 'es'];
        this.imageSearch = { pending: mode, candidate: null };

        Livewire.dispatch('request-featured-image', {
            mode,
            titles: Object.fromEntries(locales.map((locale) => [locale, this.text(locale, 'title')])),
            excerpts: Object.fromEntries(locales.map((locale) => [locale, this.text(locale, 'excerpt')])),
            currentImageId: this.$wire.featuredImageId ?? null,
        });
    },

    // L'image est prete. Sans image actuelle, elle prend sa place ; sinon elle se montre d'abord, et l'actuelle
    // reste tant qu'on ne choisit pas la nouvelle. Rien n'est enregistre avant l'article.
    async imageFound({ imageId }) {
        this.imageSearch.pending = null;
        const hasCurrent = this.$wire.featuredImageId !== null && this.images[this.$wire.featuredImageId] !== undefined;

        if (! hasCurrent) {
            const image = await this.$wire.useFoundImage(imageId);

            if (image) {
                this.images[image.id] = image;
                this.dirty = true;
            }

            return;
        }

        const image = await this.$wire.foundImage(imageId);

        if (image) {
            this.images[image.id] = image;
            this.imageSearch.candidate = image;
            this.$dispatch('ui-open-modal', 'use-found-image');
        }
    },

    async useCandidateImage() {
        const candidate = this.imageSearch.candidate;
        this.$dispatch('ui-close-modal', 'use-found-image');

        if (! candidate) {
            return;
        }

        const image = await this.$wire.useFoundImage(candidate.id);
        this.imageSearch.candidate = null;

        if (image) {
            this.images[image.id] = image;
            this.dirty = true;
        }
    },

    keepCurrentImage() {
        this.imageSearch.candidate = null;
        this.$dispatch('ui-close-modal', 'use-found-image');
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
