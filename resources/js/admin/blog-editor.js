// Editeur de contenu d'un article du blog (Tiptap). Un editeur par langue.
// Le document produit porte les memes noms d'elements que le modele du serveur
// (App\Services\Blog\Content\BlogContentSchema) : paragraphe, intertitres de niveaux 2 et 3, listes,
// citation, image du blog, bloc « image et texte », gras, italique, lien, saut de ligne. Le serveur reste
// seul juge : il renormalise tout document recu.

import { Editor, Node, mergeAttributes } from '@tiptap/core';
import { NodeSelection, TextSelection } from '@tiptap/pm/state';
import StarterKit from '@tiptap/starter-kit';

// Evenements que l'image du texte envoie au formulaire (ecoutes sur la racine du formulaire).
export const IMAGE_DESCRIBE_EVENT = 'blog-image-describe';
export const IMAGE_RECROP_EVENT = 'blog-image-recrop';

const IMAGE = 'blogImage';
const IMAGE_TEXT = 'blogImageText';

// Largeurs qu'une image du texte peut prendre dans l'article. « full » ne s'ecrit pas dans le document :
// c'est l'absence d'attribut (meme regle que le serveur, BlogContentSchema::IMAGE_WIDTHS).
const IMAGE_WIDTHS = ['full', 'medium', 'small'];

// Positions d'une image REDUITE. Au centre, c'est une image seule (blogImage). A gauche ou a droite, c'est un
// bloc « image et texte » (blogImageText) : l'image porte sa propre zone de texte de l'autre cote, et le texte
// d'avant ou d'apres ne passe jamais a cote d'elle (decision de Micha du 19/11/2026, BlogContentSchema::IMAGE_SIDES).
const IMAGE_POSITIONS = ['left', 'center', 'right'];

// Volets de reglages ouverts, par image : la vue d'une image est recreee a chaque changement de largeur ou de
// position, et le volet doit rester ouvert pendant qu'on regle.
const openPanels = new Set();

// Un document a du contenu des qu'il porte un texte non vide ou une image : un paragraphe vide n'en est pas.
export const documentHasContent = (node) => {
    if (! node || typeof node !== 'object') {
        return false;
    }

    if (node.type === IMAGE || node.type === IMAGE_TEXT) {
        return true;
    }

    if (node.type === 'text') {
        return typeof node.text === 'string' && node.text.trim() !== '';
    }

    return Array.isArray(node.content) && node.content.some(documentHasContent);
};

const isImageNode = (node) => node?.type.name === IMAGE || node?.type.name === IMAGE_TEXT;

// Remplace le noeud d'image a la position donnee par d'autres noeuds, en une seule transaction.
const replaceNode = (editor, position, node, replacement, select) => {
    const tr = editor.state.tr.replaceWith(position, position + node.nodeSize, replacement);

    select?.(tr);
    editor.view.dispatch(tr.scrollIntoView());
    editor.view.focus();
};

// Image seule <-> bloc « image et texte ». Le texte d'un bloc qui redevient une image seule passe sous elle :
// rien n'est perdu. Une image qui devient un bloc recoit un paragraphe vide, ou le curseur se place.
const toImageText = (editor, position, node, width, side) => {
    const { schema } = editor.state;
    const block = schema.nodes[IMAGE_TEXT].create({ imageId: node.attrs.imageId, width, side }, schema.nodes.paragraph.create());

    replaceNode(editor, position, node, block, (tr) => tr.setSelection(TextSelection.create(tr.doc, position + 2)));
};

const toImage = (editor, position, node, width) => {
    const { schema } = editor.state;
    const image = schema.nodes[IMAGE].create({ imageId: node.attrs.imageId, width: width === 'full' ? null : width });
    const text = [];

    node.content.forEach((child) => {
        // Un paragraphe vide (la place du curseur dans un bloc) ne merite pas de rester sous l'image.
        if (! (child.type.name === 'paragraph' && child.content.size === 0)) {
            text.push(child);
        }
    });

    replaceNode(editor, position, node, [image, ...text], (tr) => tr.setSelection(NodeSelection.create(tr.doc, position)));
};

const setAttributes = (editor, position, node, attributes) => {
    const tr = editor.state.tr.setNodeMarkup(position, undefined, { ...node.attrs, ...attributes });

    tr.setSelection(NodeSelection.create(tr.doc, position));
    editor.view.dispatch(tr);
    editor.view.focus();
};

// Nouvelle largeur. En pleine largeur, il n'y a plus de place a cote : un bloc redevient une image seule.
const changeWidth = (editor, position, node, width) => {
    if (node.type.name === IMAGE) {
        setAttributes(editor, position, node, { width: width === 'full' ? null : width });
    } else if (width === 'full') {
        toImage(editor, position, node, width);
    } else {
        setAttributes(editor, position, node, { width });
    }
};

const changePosition = (editor, position, node, side) => {
    if (node.type.name === IMAGE) {
        if (side !== 'center' && node.attrs.width) {
            toImageText(editor, position, node, node.attrs.width, side);
        }
    } else if (side === 'center') {
        toImage(editor, position, node, node.attrs.width);
    } else {
        setAttributes(editor, position, node, { side });
    }
};

// « Monter » et « Descendre » : le bloc passe au-dessus ou en dessous de son voisin et reste selectionne. Plus
// sur que le glisser, qui reste possible par la poignee (choix de Micha du 19/11).
const move = (editor, position, direction) => {
    const { state } = editor;
    const node = state.doc.nodeAt(position);
    const $position = state.doc.resolve(position);
    const index = $position.index();
    const parent = $position.parent;

    if (! node || (direction < 0 && index === 0) || (direction > 0 && index >= parent.childCount - 1)) {
        return;
    }

    const neighbour = parent.child(index + direction);
    let tr;
    let target;

    if (direction < 0) {
        target = position - neighbour.nodeSize;
        tr = state.tr.delete(position, position + node.nodeSize).insert(target, node);
    } else {
        target = position + neighbour.nodeSize;
        tr = state.tr.insert(position + node.nodeSize + neighbour.nodeSize, node).delete(position, position + node.nodeSize);
    }

    tr.setSelection(NodeSelection.create(tr.doc, target));
    editor.view.dispatch(tr.scrollIntoView());
    editor.view.focus();
};

const button = (label, onClick) => {
    const element = document.createElement('button');

    element.type = 'button';
    element.textContent = label;
    element.addEventListener('click', onClick);

    return element;
};

// Un groupe de boutons dont celui du choix en place est marque.
const choices = (className, legend, values, labelOf, current, onChoose) => {
    const group = document.createElement('span');

    group.className = className;
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', legend);

    values.forEach((value) => {
        const choice = button(labelOf(value), () => onChoose(value));

        choice.dataset.value = value;
        choice.setAttribute('aria-pressed', String(value === current));
        group.append(choice);
    });

    return group;
};

const ICON_SETTINGS = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>';
const ICON_HANDLE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" /></svg>';

// La figure d'une image dans l'editeur, commune a l'image seule et au bloc « image et texte » : l'apercu, une
// poignee pour glisser, un bouton qui ouvre le volet des reglages, et le volet, ferme par defaut (decision de
// Micha du 19/11 : voir l'article presque comme il sera publie). Le bouton porte un point tant que la
// description manque dans la langue de l'editeur. Le volet contient la largeur, la position, Monter et
// Descendre, Recadrer, Description, Retirer, et le texte de la description.
const imageFigure = ({ node, editor, getPos, images, locale, labels }) => {
    const figure = document.createElement('figure');
    const picture = document.createElement('img');
    const handle = document.createElement('span');
    const toggle = document.createElement('button');
    const panel = document.createElement('figcaption');
    const text = document.createElement('span');
    const actions = document.createElement('span');
    const width = node.attrs.width ?? 'full';
    const position = node.type.name === IMAGE_TEXT ? node.attrs.side : 'center';
    const at = () => getPos();

    // Le noeud tel qu'il est au moment du geste : la vue d'un bloc survit a la saisie dans sa zone de texte, et
    // sa taille change avec elle.
    const act = (gesture) => {
        const start = at();
        const current = typeof start === 'number' ? editor.state.doc.nodeAt(start) : null;

        if (isImageNode(current)) {
            gesture(start, current);
        }
    };

    figure.className = 'blog-editor-image';
    figure.contentEditable = 'false';
    figure.dataset.imageId = String(node.attrs.imageId);
    figure.dataset.width = width;
    picture.draggable = false;

    // Le navigateur ne lance un glisser que depuis un element glissable ; ProseMirror deplace alors le bloc
    // entier, celui de l'element le plus proche.
    handle.className = 'blog-editor-image-handle';
    handle.draggable = true;
    handle.dataset.dragHandle = '';
    handle.title = labels.handle;
    handle.setAttribute('aria-hidden', 'true');
    handle.innerHTML = ICON_HANDLE;

    toggle.type = 'button';
    toggle.className = 'blog-editor-image-toggle';
    toggle.title = labels.settings;
    toggle.setAttribute('aria-label', labels.settings);
    toggle.innerHTML = ICON_SETTINGS;

    panel.className = 'blog-editor-image-panel';
    actions.className = 'blog-editor-image-actions';

    const open = (state) => {
        panel.hidden = ! state;
        toggle.setAttribute('aria-expanded', String(state));
        state ? openPanels.add(node.attrs.imageId) : openPanels.delete(node.attrs.imageId);
    };

    toggle.addEventListener('click', () => open(panel.hidden));

    const widths = choices('blog-editor-image-widths', labels.width, IMAGE_WIDTHS, (value) => labels.widths[value], width, (value) => {
        act((start, current) => changeWidth(editor, start, current, value));
    });

    // La position n'existe que pour une image reduite : en pleine largeur, il n'y a rien a cote.
    const positions = choices('blog-editor-image-widths blog-editor-image-aligns', labels.position, IMAGE_POSITIONS, (value) => labels.positions[value], position, (value) => {
        act((start, current) => changePosition(editor, start, current, value));
    });

    positions.hidden = width === 'full';

    const moves = document.createElement('span');

    moves.className = 'blog-editor-image-moves';
    moves.append(
        button(labels.moveUp, () => act((start) => move(editor, start, -1))),
        button(labels.moveDown, () => act((start) => move(editor, start, 1))),
    );

    const recrop = button(labels.recrop, () => {
        figure.dispatchEvent(new CustomEvent(IMAGE_RECROP_EVENT, { bubbles: true, detail: { imageId: node.attrs.imageId, locale, position: at() } }));
    });

    recrop.dataset.action = 'recrop';

    const describe = button(labels.describe, () => {
        figure.dispatchEvent(new CustomEvent(IMAGE_DESCRIBE_EVENT, { bubbles: true, detail: { imageId: node.attrs.imageId } }));
    });

    const remove = button(labels.remove, () => act((start, current) => {
        openPanels.delete(current.attrs.imageId);
        editor.chain().focus().deleteRange({ from: start, to: start + current.nodeSize }).run();
    }));

    const paint = () => {
        const image = images()[node.attrs.imageId];
        const alt = image?.alt?.[locale] ?? '';

        picture.src = image?.url ?? '';
        picture.alt = alt;
        text.textContent = alt === '' ? labels.missing : alt;
        text.classList.toggle('blog-editor-image-missing', alt === '');
        toggle.toggleAttribute('data-missing', alt === '');
        // Toute image qui a un fichier se recadre (sa source gardee, sinon sa plus grande variante).
        recrop.hidden = ! image?.source;
    };

    actions.append(widths, positions, moves, recrop, describe, remove);
    panel.append(text, actions);
    figure.append(picture, handle, toggle, panel);
    open(openPanels.has(node.attrs.imageId));
    paint();

    // Le formulaire rappelle `repaint` apres un changement de description.
    figure.repaint = paint;

    return figure;
};

// Les boutons de la figure sont a elle : ProseMirror ne doit ni deplacer la selection ni traiter le clic. Un
// clic sur l'image la selectionne toujours, et la poignee lui laisse le glisser.
const stopFigureEvents = (figure) => (event) => event.target instanceof Element
    && figure.contains(event.target)
    && event.target.closest('button') !== null;

// Image du blog dans le texte, seule : le document ne porte que son identifiant et, si elle est reduite, sa
// largeur. Elle est toujours centree. L'apercu et la description viennent de la liste d'images que tient le
// formulaire (`images()`), dans la langue de l'editeur.
const blogImage = (options) => Node.create({
    name: IMAGE,
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
            // Une image reduite placee a gauche ou a droite s'insere en bloc « image et texte ».
            insertBlogImage: (imageId, width = null, position = null) => ({ commands }) => {
                const reduced = width === 'full' ? null : width;

                if (reduced && (position === 'left' || position === 'right')) {
                    return commands.insertContent({ type: IMAGE_TEXT, attrs: { imageId, width: reduced, side: position }, content: [{ type: 'paragraph' }] });
                }

                return commands.insertContent({ type: this.name, attrs: { imageId, width: reduced } });
            },
        };
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const figure = imageFigure({ node, editor, getPos, ...options });

            return { dom: figure, stopEvent: stopFigureEvents(figure) };
        };
    },
});

// Bloc « image et texte » : une image reduite, a gauche ou a droite, et sa propre zone de texte de l'autre cote
// (paragraphes, listes, intertitres). Un seul noeud : il se deplace d'un seul tenant, et le texte d'avant ou
// d'apres ne passe jamais dans la zone. `isolating` : Entree, Retour arriere et le collage restent dans la zone.
const blogImageText = (options) => Node.create({
    name: IMAGE_TEXT,
    group: 'block',
    content: '(paragraph | heading | bulletList | orderedList)+',
    draggable: true,
    isolating: true,
    defining: true,

    addAttributes() {
        return {
            imageId: {
                default: null,
                parseHTML: (element) => Number(element.getAttribute('data-image-id')) || null,
                renderHTML: (attributes) => ({ 'data-image-id': attributes.imageId }),
            },
            width: {
                default: 'small',
                parseHTML: (element) => (element.getAttribute('data-width') === 'medium' ? 'medium' : 'small'),
                renderHTML: (attributes) => ({ 'data-width': attributes.width }),
            },
            side: {
                default: 'left',
                parseHTML: (element) => (element.getAttribute('data-side') === 'right' ? 'right' : 'left'),
                renderHTML: (attributes) => ({ 'data-side': attributes.side }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-blog-image-text]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes({ 'data-blog-image-text': '' }, HTMLAttributes), 0];
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const dom = document.createElement('div');
            const body = document.createElement('div');
            const figure = imageFigure({ node, editor, getPos, ...options });

            dom.className = 'blog-editor-image-text';
            dom.dataset.width = node.attrs.width;
            dom.dataset.side = node.attrs.side;
            body.className = 'blog-editor-image-text-body';
            body.setAttribute('aria-label', options.labels.sideText);
            dom.append(figure, body);

            return {
                dom,
                contentDOM: body,
                stopEvent: stopFigureEvents(figure),
                // Le volet qui s'ouvre ou la description qui se repeint ne sont pas du contenu.
                ignoreMutation: (mutation) => mutation.type !== 'selection' && figure.contains(mutation.target),
            };
        };
    },
});

// Redessine les images d'un editeur (apres l'enregistrement de leurs descriptions).
export const repaintImages = (editor) => {
    editor.view.dom.querySelectorAll('figure.blog-editor-image').forEach((figure) => figure.repaint?.());
};

// Met une autre image a la place de celle qui se trouve a cette position (apres un nouveau cadrage), seule ou
// dans son bloc.
export const replaceImageAt = (editor, position, imageId) => editor.chain().focus().command(({ tr, state }) => {
    const node = typeof position === 'number' ? state.doc.nodeAt(position) : null;

    if (! isImageNode(node)) {
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
        blogImageText({ images, locale, labels }),
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
