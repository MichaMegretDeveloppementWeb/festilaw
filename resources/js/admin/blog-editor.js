// Editeur de contenu d'un article du blog (Tiptap). Un editeur par langue.
// Le document produit porte les memes noms d'elements que le modele du serveur
// (App\Services\Blog\Content\BlogContentSchema) : paragraphe, intertitres de niveaux 2 et 3, listes,
// citation, image du blog, gras, italique, lien, saut de ligne. Le serveur reste seul juge : il
// renormalise tout document recu.

import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// Evenements que l'image du texte envoie au formulaire (ecoutes sur la racine du formulaire).
export const IMAGE_DESCRIBE_EVENT = 'blog-image-describe';

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

// Image du blog dans le texte : le document ne porte que son identifiant. L'apercu et la description
// viennent de la liste d'images que tient le formulaire (`images()`), dans la langue de l'editeur.
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
            insertBlogImage: (imageId) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { imageId } }),
        };
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const figure = document.createElement('figure');
            const picture = document.createElement('img');
            const caption = document.createElement('figcaption');
            const text = document.createElement('span');
            const actions = document.createElement('span');
            const describe = document.createElement('button');
            const remove = document.createElement('button');

            figure.className = 'blog-editor-image';
            figure.contentEditable = 'false';
            figure.dataset.imageId = String(node.attrs.imageId);
            picture.draggable = false;
            actions.className = 'blog-editor-image-actions';
            describe.type = 'button';
            describe.textContent = labels.describe;
            remove.type = 'button';
            remove.textContent = labels.remove;

            const paint = () => {
                const image = images()[node.attrs.imageId];
                const alt = image?.alt?.[locale] ?? '';

                picture.src = image?.url ?? '';
                picture.alt = alt;
                text.textContent = alt === '' ? labels.missing : alt;
                text.classList.toggle('blog-editor-image-missing', alt === '');
            };

            describe.addEventListener('click', () => {
                figure.dispatchEvent(new CustomEvent(IMAGE_DESCRIBE_EVENT, { bubbles: true, detail: { imageId: node.attrs.imageId } }));
            });
            remove.addEventListener('click', () => {
                const position = getPos();

                if (typeof position === 'number') {
                    editor.chain().focus().deleteRange({ from: position, to: position + node.nodeSize }).run();
                }
            });

            actions.append(describe, remove);
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
