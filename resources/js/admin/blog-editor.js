// Editeur de contenu d'un article du blog (Tiptap). Un editeur par langue.
// Le document produit porte les memes noms d'elements que le modele du serveur
// (App\Services\Blog\Content\BlogContentSchema) : paragraphe, intertitres de niveaux 2 et 3, listes,
// citation, image du blog, gras, italique, lien, saut de ligne. Le serveur reste seul juge : il
// renormalise tout document recu.

import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// Evenements que l'image du texte envoie au formulaire (ecoutes sur la racine du formulaire).
export const IMAGE_DESCRIBE_EVENT = 'blog-image-describe';
export const IMAGE_RECROP_EVENT = 'blog-image-recrop';

// Largeurs qu'une image du texte peut prendre dans l'article. « full » ne s'ecrit pas dans le document :
// c'est l'absence d'attribut (meme regle que le serveur, BlogContentSchema::IMAGE_WIDTHS).
const IMAGE_WIDTHS = ['full', 'medium', 'small'];

// Un document a du contenu des qu'il porte un texte non vide ou une image : un paragraphe vide n'en est pas.
export const documentHasContent = (node) => {
    if (! node || typeof node !== 'object') {
        return false;
    }

    if (node.type === 'blogImage') {
        return true;
    }

    if (node.type === 'text') {
        return typeof node.text === 'string' && node.text.trim() !== '';
    }

    return Array.isArray(node.content) && node.content.some(documentHasContent);
};

// Image du blog dans le texte : le document ne porte que son identifiant et, si elle est reduite, sa largeur.
// L'apercu et la description viennent de la liste d'images que tient le formulaire (`images()`), dans la
// langue de l'editeur.
const blogImage = ({ images, locale, labels }) => Node.create({
    name: 'blogImage',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,

    addAttributes() {
        return {
            imageId: {
                default: null,
                parseHTML: (element) => Number(element.getAttribute('data-image-id')) || null,
                renderHTML: (attributes) => ({ 'data-image-id': attributes.imageId }),
            },
            width: {
                default: null,
                parseHTML: (element) => (IMAGE_WIDTHS.includes(element.getAttribute('data-width')) && element.getAttribute('data-width') !== 'full' ? element.getAttribute('data-width') : null),
                renderHTML: (attributes) => (attributes.width ? { 'data-width': attributes.width } : {}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-blog-image]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['figure', mergeAttributes({ 'data-blog-image': '' }, HTMLAttributes)];
    },

    addCommands() {
        return {
            insertBlogImage: (imageId, width = null) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { imageId, width: width === 'full' ? null : width } }),
        };
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const figure = document.createElement('figure');
            const picture = document.createElement('img');
            const caption = document.createElement('figcaption');
            const text = document.createElement('span');
            const actions = document.createElement('span');
            const current = node.attrs.width ?? 'full';

            // Remplace les attributs du noeud (largeur). L'image reste a sa place dans le texte.
            const setAttributes = (attributes) => {
                const position = getPos();

                if (typeof position === 'number') {
                    editor.chain().focus().command(({ tr }) => {
                        tr.setNodeMarkup(position, undefined, { ...node.attrs, ...attributes });

                        return true;
                    }).run();
                }
            };

            const button = (label, onClick) => {
                const element = document.createElement('button');

                element.type = 'button';
                element.textContent = label;
                element.addEventListener('click', onClick);

                return element;
            };

            figure.className = 'blog-editor-image';
            figure.contentEditable = 'false';
            figure.dataset.imageId = String(node.attrs.imageId);
            figure.dataset.width = current;
            picture.draggable = false;
            actions.className = 'blog-editor-image-actions';

            // Largeur dans l'article : trois boutons, celui de la largeur en place est marque.
            const widths = document.createElement('span');

            widths.className = 'blog-editor-image-widths';
            widths.setAttribute('role', 'group');
            widths.setAttribute('aria-label', labels.width);

            IMAGE_WIDTHS.forEach((width) => {
                const choice = button(labels.widths[width], () => setAttributes({ width: width === 'full' ? null : width }));

                choice.dataset.width = width;
                choice.setAttribute('aria-pressed', String(width === current));
                widths.append(choice);
            });

            const recrop = button(labels.recrop, () => {
                figure.dispatchEvent(new CustomEvent(IMAGE_RECROP_EVENT, { bubbles: true, detail: { imageId: node.attrs.imageId, locale, position: getPos() } }));
            });

            recrop.dataset.action = 'recrop';

            const describe = button(labels.describe, () => {
                figure.dispatchEvent(new CustomEvent(IMAGE_DESCRIBE_EVENT, { bubbles: true, detail: { imageId: node.attrs.imageId } }));
            });

            const remove = button(labels.remove, () => {
                const position = getPos();

                if (typeof position === 'number') {
                    editor.chain().focus().deleteRange({ from: position, to: position + node.nodeSize }).run();
                }
            });

            const paint = () => {
                const image = images()[node.attrs.imageId];
                const alt = image?.alt?.[locale] ?? '';

                picture.src = image?.url ?? '';
                picture.alt = alt;
                text.textContent = alt === '' ? labels.missing : alt;
                text.classList.toggle('blog-editor-image-missing', alt === '');
                // Une image sans source gardee (anterieure au recadrage) ne se recadre pas.
                recrop.hidden = ! image?.source;
            };

            actions.append(widths, recrop, describe, remove);
            caption.append(text, actions);
            figure.append(picture, caption);
            paint();

            // Le formulaire rappelle `repaint` apres un changement de description.
            figure.repaint = paint;

            return { dom: figure };
        };
    },
});

// Redessine les images d'un editeur (apres l'enregistrement de leurs descriptions).
export const repaintImages = (editor) => {
    editor.view.dom.querySelectorAll('figure.blog-editor-image').forEach((figure) => figure.repaint?.());
};

// Met une autre image a la place de celle qui se trouve a cette position (apres un nouveau cadrage).
export const replaceImageAt = (editor, position, imageId) => editor.chain().focus().command(({ tr, state }) => {
    const node = typeof position === 'number' ? state.doc.nodeAt(position) : null;

    if (! node || node.type.name !== 'blogImage') {
        return false;
    }

    tr.setNodeMarkup(position, undefined, { ...node.attrs, imageId });

    return true;
}).run();

export const createBlogEditor = ({ element, content, locale, images, labels, onChange, onSelection }) => new Editor({
    element,
    content: content ?? '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
            strike: false,
            underline: false,
            horizontalRule: false,
            link: {
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
                protocols: ['http', 'https', 'mailto'],
                HTMLAttributes: { rel: null, target: null },
            },
        }),
        blogImage({ images, locale, labels }),
    ],
    editorProps: {
        attributes: {
            class: 'blog-editor-content',
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': labels.editor,
            lang: locale,
        },
    },
    onUpdate: ({ editor }) => onChange(editor.getJSON()),
    onSelectionUpdate: onSelection,
    onTransaction: onSelection,
});
