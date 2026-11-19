<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

/**
 * Modele ferme du contenu d'un article : les seuls elements et marques acceptes. Les noms sont ceux
 * de l'editeur Tiptap, pour que le document du back-office et celui produit par l'IA soient le meme.
 * Tout ce qui n'est pas liste ici est retire a l'enregistrement.
 */
final class BlogContentSchema
{
    public const DOCUMENT = 'doc';

    public const PARAGRAPH = 'paragraph';

    public const HEADING = 'heading';

    public const BULLET_LIST = 'bulletList';

    public const ORDERED_LIST = 'orderedList';

    public const LIST_ITEM = 'listItem';

    public const BLOCKQUOTE = 'blockquote';

    public const IMAGE = 'blogImage';

    /**
     * Image reduite avec son propre texte a cote d'elle, a gauche ou a droite (decision de Micha du 19/11/2026).
     * Le texte d'avant ou d'apres ne passe jamais a cote de l'image, comme il le faisait quand elle flottait :
     * le bloc se deplace d'un seul tenant, image et texte ensemble.
     */
    public const IMAGE_TEXT = 'blogImageText';

    public const TEXT = 'text';

    public const HARD_BREAK = 'hardBreak';

    public const MARK_BOLD = 'bold';

    public const MARK_ITALIC = 'italic';

    public const MARK_LINK = 'link';

    /**
     * Largeurs reduites qu'une image du texte peut porter (attribut `width`). Sans attribut, l'image prend
     * toute la colonne : c'est le cas par defaut, et celui de tout article anterieur a cet attribut.
     */
    public const IMAGE_WIDTHS = ['medium', 'small'];

    /**
     * Cote de l'image dans un bloc « image et texte » (attribut `side`), son texte etant de l'autre cote. Une
     * image seule n'a pas de position : elle est centree.
     */
    public const IMAGE_SIDES = ['left', 'right'];

    /** Niveaux d'intertitre autorises (le titre de la page est le seul h1). */
    public const HEADING_LEVELS = [2, 3];

    /** Ordre canonique des marques d'un texte (sert a comparer et a fusionner deux textes voisins). */
    public const MARK_ORDER = [self::MARK_LINK, self::MARK_BOLD, self::MARK_ITALIC];
}
