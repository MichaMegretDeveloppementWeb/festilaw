/**
 * La barre par-dessus le contenu, entre lg et 1 400 pixels, reprise de Chouchoute-toi (demande de Micha
 * du 20/11) : ☰ l'ouvre entiere au-dessus de la page sans rien retenir, et un clic a cote, un lien, Echap
 * ou ☰ de nouveau la replient. Au-dessus et au-dessous de cette bande, la commande du kit reste seule.
 * La feuille fait le dessin (`resources/css/admin/sidebar.css`).
 */

const BAND = '(64rem <= width < 87.5rem)';
const OVER = 'data-admin-sidebar-over';
const STATE = 'data-ui-sidebar';
const TOGGLE = '[data-ui-sidebar-toggle]';

const frame = () => document.querySelector('.ui-sidebar-frame');

/** La barre est-elle ouverte par-dessus le contenu. */
export function isOver(box) {
    return box?.hasAttribute(OVER) ?? false;
}

function sayTheState(open) {
    document.querySelectorAll(TOGGLE).forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
}

/** Ouvre la barre par-dessus le contenu par l'etat ouvert du kit, en gardant celui qu'il remplace. */
export function openOver(box) {
    box.setAttribute(OVER, box.getAttribute(STATE) ?? '');
    box.setAttribute(STATE, 'expanded');
    sayTheState(true);
}

/** Replie la barre, l'etat du kit redevenant celui d'avant. */
export function closeOver(box) {
    const was = box.getAttribute(OVER);

    box.removeAttribute(OVER);

    if (was) {
        box.setAttribute(STATE, was);
    } else {
        box.removeAttribute(STATE);
    }

    sayTheState(false);
}

/** Confie a ☰ la barre par-dessus le contenu tant que la fenetre reste dans la bande. */
export function holdTheBarOverTheContent(band = window.matchMedia(BAND)) {
    const onClick = (event) => {
        const box = frame();

        if (! band.matches || ! box || ! (event.target instanceof Element)) {
            return;
        }

        if (event.target.closest(TOGGLE)) {
            event.preventDefault();
            event.stopImmediatePropagation();

            if (isOver(box)) {
                closeOver(box);
            } else {
                openOver(box);
            }

            return;
        }

        if (! isOver(box)) {
            return;
        }

        // Un lien de la barre mene ailleurs et la page suivante s'ouvre repliee ; un clic a cote de la
        // barre tombe sur le voile, et ne fait que la replier.
        if (! event.target.closest('.ui-sidebar')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            closeOver(box);
        } else if (event.target.closest('a[href]')) {
            closeOver(box);
        }
    };

    const onKey = (event) => {
        const box = frame();

        if (event.key === 'Escape' && isOver(box)) {
            closeOver(box);
            document.querySelector(TOGGLE)?.focus();
        }
    };

    const onBand = () => {
        const box = frame();

        if (! band.matches && isOver(box)) {
            closeOver(box);
        }
    };

    document.addEventListener('click', onClick, true);
    document.addEventListener('keydown', onKey);
    band.addEventListener('change', onBand);

    return () => {
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('keydown', onKey);
        band.removeEventListener('change', onBand);
    };
}
